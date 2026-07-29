<?php

use App\Data\Routing\AttemptResult;
use App\Data\Routing\ChatRequestData;
use App\Data\Routing\ConfigEntry;
use App\Exceptions\AllProvidersFailedException;
use App\Models\ProviderFailure;
use App\Services\Routing\Router;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('returns successful response on first entry', function (): void {
    Http::fake([
        '*integrate.api.nvidia.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hello']]]]),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
    ]);

    $router = app(Router::class);
    $data = ChatRequestData::fromRequest([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => false,
    ]);

    $result = $router->chat($data);

    expect($result->success)->toBeTrue();
    expect($result->body)->toBeJson();
});

it('fails over to next entry when first fails', function (): void {
    Http::fake([
        '*integrate.api.nvidia.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
        '*openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Hello from OR']]]]),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat', 'api_key' => 'or-test-1'],
    ]);

    $router = app(Router::class);
    $data = ChatRequestData::fromRequest([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => false,
    ]);

    $result = $router->chat($data);

    expect($result->success)->toBeTrue();
    expect($result->body)->toContain('Hello from OR');
});

it('records failures in the database', function (): void {
    Http::fake([
        'integrate.api.nvidia.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
    ]);

    try {
        $router = app(Router::class);
        $data = ChatRequestData::fromRequest([
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'stream' => false,
        ]);
        $router->chat($data);
    } catch (AllProvidersFailedException) {
        // expected
    }

    $failures = ProviderFailure::where('provider', 'nvidia')->get();

    expect($failures)->not->toBeEmpty();
    expect($failures->first()->error_type)->toBe('rate_limit');
});

it('skips entries in cooldown', function (): void {
    ProviderFailure::create([
        'provider' => 'nvidia',
        'model' => 'nvidia/llama-3',
        'api_key_mask' => 'nv-t...st-1',
        'error_type' => 'rate_limit',
        'error_message' => 'Rate limit',
        'failed_at' => now()->subMinutes(2),
    ]);

    // Make the cooldown check trip by setting max_failures=1 and window large
    config()->set('relayai.max_failures', 1);
    config()->set('relayai.window_minutes', 60);
    config()->set('relayai.cooldown_minutes', 30);

    // Create a second failure so count >= max_failures.
    // api_key_mask must match what ConfigEntry::fromArray produces for 'nv-test-1':
    // substr('nv-test-1', 0, 4) = 'nv-t', substr('nv-test-1', -4) = 'st-1' -> 'nv-t...st-1'
    ProviderFailure::create([
        'provider' => 'nvidia',
        'model' => 'nvidia/llama-3',
        'api_key_mask' => 'nv-t...st-1',
        'error_type' => 'rate_limit',
        'error_message' => 'Rate limit',
        'failed_at' => now()->subMinutes(1),
    ]);

    Http::fake([
        '*openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'OR fallback']]]]),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat', 'api_key' => 'or-test-1'],
    ]);

    $router = app(Router::class);
    $data = ChatRequestData::fromRequest([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => false,
    ]);

    // Should skip nvidia (cooldown) and fall to openrouter
    $result = $router->chat($data);

    expect($result->success)->toBeTrue();
    expect($result->provider)->toBe('openrouter');
});

it('throws AllProvidersFailedException when all fail', function (): void {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Error']], 500),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat', 'api_key' => 'or-test-1'],
    ]);

    $router = app(Router::class);
    $data = ChatRequestData::fromRequest([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => false,
    ]);

    expect(fn () => $router->chat($data))->toThrow(AllProvidersFailedException::class);
});

it('handles network timeouts', function (): void {
    Http::fake([
        '*' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
    ]);

    $router = app(Router::class);
    $data = ChatRequestData::fromRequest([
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => false,
    ]);

    expect(fn () => $router->chat($data))->toThrow(AllProvidersFailedException::class);
});

it('config entry identity is deterministic', function (): void {
    $entry = ConfigEntry::fromArray([
        'provider' => 'nvidia',
        'model' => 'nvidia/llama-3',
        'api_key' => 'nv-test-key-12345',
    ]);

    expect($entry->identity())->toBe('nvidia:nvidia/llama-3:nv-t...2345');
});

it('chat request data builds correct upstream payload', function (): void {
    $data = ChatRequestData::fromRequest([
        'model' => 'gpt-4',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'temperature' => 0.7,
        'max_tokens' => 100,
        'stream' => true,
    ]);

    $payload = $data->toUpstreamPayload();

    expect($payload['model'])->toBe('gpt-4');
    expect($payload['temperature'])->toBe(0.7);
    expect($payload['max_tokens'])->toBe(100);
    expect($payload['stream'])->toBeTrue();
});

it('attempt result is cooldown candidate only when failed and retryable', function (): void {
    $success = new AttemptResult(success: true, provider: 'nvidia', model: 'm', apiKey: 'k');
    $failedNonRetryable = new AttemptResult(success: false, retryable: false, provider: 'nvidia', model: 'm', apiKey: 'k');
    $failedRetryable = new AttemptResult(success: false, retryable: true, provider: 'nvidia', model: 'm', apiKey: 'k');

    expect($success->isCooldownCandidate())->toBeFalse();
    expect($failedNonRetryable->isCooldownCandidate())->toBeFalse();
    expect($failedRetryable->isCooldownCandidate())->toBeTrue();
});
