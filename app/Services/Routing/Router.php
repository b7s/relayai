<?php

namespace App\Services\Routing;

use App\Actions\Routing\AttemptChat;
use App\Actions\Routing\RecordFailure;
use App\Data\Routing\AttemptResult;
use App\Data\Routing\ChatRequestData;
use App\Data\Routing\ConfigEntry;
use App\Exceptions\AllProvidersFailedException;
use App\Models\ProviderFailure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

final readonly class Router
{
    public function __construct(
        private AttemptChat $attemptChat,
        private RecordFailure $recordFailure,
    ) {}

    public function chat(ChatRequestData $request): AttemptResult
    {
        $entries = $this->resolveEntries($request);

        foreach ($entries as $entry) {
            $result = $this->tryEntry($entry, $request);

            if ($result->success) {
                return $result;
            }
        }

        return $this->wraparound($entries, $request);
    }

    public function stream(ChatRequestData $request, callable $onChunk): void
    {
        $entries = $this->resolveEntries($request);
        $lastFailedResult = null;

        foreach ($entries as $entry) {
            $streamResult = $this->tryEntryStream($entry, $request, $onChunk);
            $entrySuccess = $streamResult[0];
            $entryResult = $streamResult[2];

            if ($entrySuccess) {
                return;
            }

            if ($entryResult !== null) {
                $lastFailedResult = $entryResult;
            }
        }

        $message = $lastFailedResult !== null ?
            $lastFailedResult->errorMessage :
            'All providers failed during streaming';

        throw new AllProvidersFailedException($message);
    }

    private function tryEntry(ConfigEntry $entry, ChatRequestData $request): AttemptResult
    {
        for ($i = 0; $i < config('relayai.retries', 3); $i++) {
            $result = ($this->attemptChat)($entry, $request);

            if ($result->success) {
                return $result;
            }

            if ($result->isCooldownCandidate()) {
                ($this->recordFailure)($result);
            }
        }

        return new AttemptResult(
            success: false,
            errorMessage: "Entry {$entry->identity()} exhausted retries",
            provider: $entry->provider,
            model: $entry->model,
            apiKey: $entry->apiKey,
        );
    }

    /**
     * @return array{bool, string, AttemptResult|null}
     */
    private function tryEntryStream(ConfigEntry $entry, ChatRequestData $request, callable $onChunk): array
    {
        $model = $request->raw['model'] ?? $entry->model;
        $url = ($this->attemptChat)->buildUrl($entry->provider, $entry->model);

        for ($i = 0; $i < config()->integer('relayai.retries', 3); $i++) {
            $payload = $request->toUpstreamPayload();
            $payload['model'] = $model;

            try {
                $response = Http::withToken($entry->apiKey)
                    ->timeout(config('relayai.timeout_seconds', 60))
                    ->withOptions(['stream' => true, 'http_errors' => false])
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                $result = new AttemptResult(
                    success: false,
                    errorMessage: $e->getMessage(),
                    retryable: true,
                    provider: $entry->provider,
                    model: $entry->model,
                    apiKey: $entry->apiKey,
                );
                ($this->recordFailure)($result);

                continue;
            }

            if ($response->status() !== 200) {
                $body = $response->json();
                $errorMsg = $body['error']['message'] ?? $response->reason();
                $result = new AttemptResult(
                    success: false,
                    body: $response->body(),
                    statusCode: $response->status(),
                    errorMessage: $errorMsg,
                    retryable: true,
                    provider: $entry->provider,
                    model: $entry->model,
                    apiKey: $entry->apiKey,
                );
                ($this->recordFailure)($result);

                continue;
            }

            $accumulatedContent = '';

            try {
                $psrResponse = $response->toPsrResponse();
                $stream = $psrResponse->getBody();

                while (! $stream->eof()) {
                    $line = $this->readLine($stream);

                    if ($line === null) {
                        break;
                    }

                    $onChunk($line);
                    $accumulatedContent .= $this->extractContent($line);
                }

                return [true, $accumulatedContent, null];
            } catch (\Throwable $e) {
                $result = new AttemptResult(
                    success: false,
                    errorMessage: 'Stream interrupted: '.$e->getMessage(),
                    retryable: true,
                    provider: $entry->provider,
                    model: $entry->model,
                    apiKey: $entry->apiKey,
                );
                ($this->recordFailure)($result);

                return [false, $accumulatedContent, $result];
            }
        }

        return [false, '', null];
    }

    /**
     * @param  ConfigEntry[]  $entries
     */
    private function wraparound(array $entries, ChatRequestData $request): AttemptResult
    {
        foreach ($entries as $entry) {
            $result = ($this->attemptChat)($entry, $request);

            if ($result->success) {
                return $result;
            }

            if ($result->isCooldownCandidate()) {
                ($this->recordFailure)($result);
            }
        }

        throw new AllProvidersFailedException(
            'All providers exhausted after wraparound',
        );
    }

    /**
     * @return ConfigEntry[]
     */
    private function resolveEntries(ChatRequestData $request): array
    {
        /** @var array<int, array<string, mixed>> $raw */
        $raw = config('relayai.entries', []);
        $entries = [];

        foreach ($raw as $item) {
            $entry = ConfigEntry::fromArray($item);

            if ($this->isInCooldown($entry)) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    private function isInCooldown(ConfigEntry $entry): bool
    {
        $window = (int) config('relayai.window_minutes', 1);
        $cooldown = (int) config('relayai.cooldown_minutes', 15);
        $maxFailures = (int) config('relayai.max_failures', 3);

        $recentCount = ProviderFailure::recentFailures(
            $entry->provider,
            $entry->model,
            $entry->apiKeyMask,
            $window,
        )->count();

        if ($recentCount < $maxFailures) {
            return false;
        }

        $latest = ProviderFailure::recentFailures(
            $entry->provider,
            $entry->model,
            $entry->apiKeyMask,
            $window,
        )->latest('failed_at')->first();

        if ($latest === null) {
            return false;
        }

        /** @var Carbon $failedAt */
        $failedAt = $latest->failed_at;

        return now()->lessThan($failedAt->addMinutes($cooldown));
    }

    private function readLine(StreamInterface $stream): ?string
    {
        $line = '';

        while (! $stream->eof()) {
            $byte = $stream->read(1);

            if ($byte === '') {
                break;
            }

            if ($byte === "\n") {
                break;
            }

            $line .= $byte;
        }

        return $line === '' && $stream->eof() ? null : $line;
    }

    private function extractContent(string $sseLine): string
    {
        if (! str_starts_with($sseLine, 'data: ')) {
            return '';
        }

        $json = substr($sseLine, 6);

        if ($json === '[DONE]') {
            return '';
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return '';
        }

        return $data['choices'][0]['delta']['content'] ?? '';
    }
}
