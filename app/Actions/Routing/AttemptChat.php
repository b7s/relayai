<?php

namespace App\Actions\Routing;

use App\Data\Routing\AttemptResult;
use App\Data\Routing\ChatRequestData;
use App\Data\Routing\ConfigEntry;
use App\Enums\Provider;
use Illuminate\Support\Facades\Http;

final readonly class AttemptChat
{
    public function __invoke(ConfigEntry $entry, ChatRequestData $request): AttemptResult
    {
        $url = $this->buildUrl($entry->provider, $entry->model);

        try {
            $response = Http::withToken($entry->apiKey)
                ->timeout(config('relayai.timeout_seconds', 60))
                ->withOptions([
                    'stream' => $request->stream,
                    'http_errors' => false,
                ])
                ->post($url, $request->toUpstreamPayload());
        } catch (\Throwable $e) {
            return new AttemptResult(
                success: false,
                errorMessage: $e->getMessage(),
                retryable: true,
                provider: $entry->provider,
                model: $entry->model,
                apiKey: $entry->apiKey,
            );
        }

        $status = $response->status();

        if ($status === 200) {
            return new AttemptResult(
                success: true,
                body: $response->body(),
                statusCode: $status,
                provider: $entry->provider,
                model: $entry->model,
                apiKey: $entry->apiKey,
            );
        }

        $body = $response->json();
        $errorMessage = $body['error']['message'] ?? $response->reason();

        return new AttemptResult(
            success: false,
            body: $response->body(),
            statusCode: $status,
            errorMessage: $errorMessage,
            retryable: true,
            provider: $entry->provider,
            model: $entry->model,
            apiKey: $entry->apiKey,
        );
    }

    public function buildUrl(string $provider, string $model): string
    {
        $baseUrl = Provider::fromName($provider)->baseUrl();

        return rtrim($baseUrl, '/').'/v1/chat/completions';
    }
}
