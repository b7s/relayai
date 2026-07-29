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
     * @param  array<string, mixed>  $entry
     */
    public static function fromArray(array $entry): self
    {
        $provider = isset($entry['provider']) && is_string($entry['provider']) ? $entry['provider'] : '';
        $model = isset($entry['model']) && is_string($entry['model']) ? $entry['model'] : '';
        $key = isset($entry['api_key']) && is_string($entry['api_key']) ? $entry['api_key'] : '';
        $mask = strlen($key) > 8
            ? substr($key, 0, 4).'...'.substr($key, -4)
            : substr($key, 0, 4).'...';

        return new self(
            provider: $provider,
            model: $model,
            apiKey: $key,
            apiKeyMask: $mask,
        );
    }

    public function identity(): string
    {
        return "{$this->provider}:{$this->model}:{$this->apiKeyMask}";
    }

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        /** @var array<int, array<string, mixed>> $entries */
        $entries = config('relayai.entries', []);

        return array_map(
            static fn (array $entry): self => self::fromArray($entry),
            $entries,
        );
    }
}
