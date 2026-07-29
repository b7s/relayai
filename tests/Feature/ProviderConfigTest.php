<?php

use App\Data\Routing\ConfigEntry;
use App\Enums\Provider;

it('resolves all providers by name', function (string $name): void {
    $provider = Provider::fromName($name);

    expect($provider)->toBeInstanceOf(Provider::class);
    expect($provider->value)->toBe($name);
})->with([
    'nvidia',
    'openrouter',
    'openai',
    'anthropic',
    'zai',
    'opencode-go',
]);

it('builds valid chat completions path for every provider', function (Provider $provider): void {
    $url = $provider->chatCompletionsPath();

    expect($url)->toEndWith('/chat/completions');
    expect(filter_var($url, FILTER_VALIDATE_URL))->not->toBeFalse();
})->with(fn (): array => array_map(
    fn (Provider $p) => [$p],
    Provider::cases(),
));

it('creates config entries from config with all providers', function (): void {
    config()->set('relayai.entries', [
        ['provider' => 'openai', 'model' => 'gpt-5', 'api_key' => 'sk-test-1'],
        ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6', 'api_key' => 'sk-test-2'],
        ['provider' => 'zai', 'model' => 'glm-5.1', 'api_key' => 'sk-test-3'],
        ['provider' => 'opencode-go', 'model' => 'deepseek-v4-flash', 'api_key' => 'sk-test-4'],
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-5'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat', 'api_key' => 'or-test-6'],
    ]);

    $entries = ConfigEntry::all();

    expect($entries)->toHaveCount(6);

    foreach ($entries as $entry) {
        expect($entry)->toBeInstanceOf(ConfigEntry::class);
        expect($entry->provider)->not->toBeEmpty();
        expect($entry->model)->not->toBeEmpty();
        expect($entry->apiKey)->not->toBeEmpty();
    }
});

it('masks short api keys correctly', function (): void {
    $entry = ConfigEntry::fromArray([
        'provider' => 'openai',
        'model' => 'gpt-5',
        'api_key' => 'sk-short',
    ]);

    expect($entry->apiKeyMask)->toBe('sk-s...');
});

it('masks long api keys correctly', function (): void {
    $entry = ConfigEntry::fromArray([
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-6',
        'api_key' => 'sk-ant-long-key-here-12345678',
    ]);

    expect($entry->apiKeyMask)->toBe('sk-a...5678');
});

it('returns models endpoint with all configured entries', function (): void {
    config()->set('relayai.entries', [
        ['provider' => 'openai', 'model' => 'gpt-5', 'api_key' => 'sk-1'],
        ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6', 'api_key' => 'sk-2'],
        ['provider' => 'zai', 'model' => 'glm-5.1', 'api_key' => 'sk-3'],
        ['provider' => 'opencode-go', 'model' => 'deepseek-v4-flash', 'api_key' => 'sk-4'],
    ]);

    $response = $this->getJson('/v1/models');

    $response->assertStatus(200);
    $response->assertJsonCount(4, 'data');

    $models = $response->json('data');
    $ownedBy = array_map(fn ($m) => $m['owned_by'], $models);
    expect($ownedBy)->toContain('openai', 'anthropic', 'zai', 'opencode-go');
});

it('accepts entries without api key', function (): void {
    $entry = ConfigEntry::fromArray([
        'provider' => 'openai',
        'model' => 'gpt-5',
    ]);

    expect($entry->apiKey)->toBe('');
    expect($entry->apiKeyMask)->toBe('...');
});
