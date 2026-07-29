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
        $model = isset($payload['model']) && is_string($payload['model']) ? $payload['model'] : '';
        /** @var array<int, array<string, mixed>> $messages */
        $messages = isset($payload['messages']) && is_array($payload['messages']) ? $payload['messages'] : [];
        $temperature = isset($payload['temperature']) && is_numeric($payload['temperature']) ? (float) $payload['temperature'] : null;
        $maxTokens = isset($payload['max_tokens']) && is_numeric($payload['max_tokens']) ? (int) $payload['max_tokens'] : null;
        $stream = isset($payload['stream']) ? (bool) $payload['stream'] : false;

        return new self(
            model: $model,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stream: $stream,
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
