<?php

namespace App\Data\Routing;

readonly class ChatRequestData
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?bool $stream = false,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            model: $payload['model'] ?? '',
            messages: $payload['messages'] ?? [],
            temperature: $payload['temperature'] ?? null,
            maxTokens: $payload['max_tokens'] ?? null,
            stream: $payload['stream'] ?? false,
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toUpstreamPayload(): array
    {
        $body = [
            'model' => $this->raw['model'] ?? $this->model,
            'messages' => $this->messages,
            'stream' => $this->stream,
        ];

        if ($this->temperature !== null) {
            $body['temperature'] = $this->temperature;
        }

        if ($this->maxTokens !== null) {
            $body['max_tokens'] = $this->maxTokens;
        }

        return $body;
    }
}
