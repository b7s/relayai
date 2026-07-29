<?php

use Illuminate\Support\Facades\Http;

it('returns ok on /up', function (): void {
    $response = $this->getJson('/up');

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

it('returns degraded when providers unreachable', function (): void {
    Http::fake([
        '*' => Http::response(status: 503),
    ]);

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
    ]);

    $response = $this->getJson('/v1/health');

    $response->assertStatus(200);
    expect($response->json('status'))->toBe('degraded');
});
