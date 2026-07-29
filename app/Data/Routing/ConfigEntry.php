<?php

namespace App\Data\Routing;

readonly class ConfigEntry
{
    public function __construct(
        public string $provider,
        public string $model,
        public string $apiKey,
        public string $apiKeyMask,
    ) {}

    /**
     * @param  array<string, string>  $entry
     */
    public static function fromArray(array $entry): self
    {
        $key = $entry['api_key'] ?? '';
        $mask = strlen($key) > 8
            ? substr($key, 0, 4).'...'.substr($key, -4)
            : substr($key, 0, 4).'...';

        return new self(
            provider: $entry['provider'],
            model: $entry['model'],
            apiKey: $key,
            apiKeyMask: $mask,
        );
    }

    public function identity(): string
    {
        return "{$this->provider}:{$this->model}:{$this->apiKeyMask}";
    }
}
