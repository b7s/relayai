<?php

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
    ]);
});

it('returns 200 on successful chat completion', function (): void {
    Http::fake([
        'integrate.api.nvidia.com/*' => Http::response([
            'id' => 'cmpl-test',
            'object' => 'chat.completion',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello']]],
        ]),
    ]);

    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['id', 'object', 'choices']);
});

it('returns 503 when all providers fail', function (): void {
    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Server error']], 500),
    ]);

    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
    ]);

    $response->assertStatus(503);
    $response->assertJsonStructure(['type', 'title', 'status', 'detail']);
});

it('returns 401 when gateway key is required and missing', function (): void {
    config()->set('relayai.gateway_key', 'secret-key');

    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
    ]);

    $response->assertStatus(401);
});

it('returns 422 on invalid request', function (): void {
    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
    ]);

    $response->assertStatus(422);
});

it('has X-Request-ID header on response', function (): void {
    Http::fake([
        'integrate.api.nvidia.com/*' => Http::response([
            'id' => 'cmpl-test',
            'object' => 'chat.completion',
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello']]],
        ]),
    ]);

    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
    ]);

    $response->assertHeader('X-Request-ID');
});

it('can stream chat completion', function (): void {
    $sseBody = "data: {\"id\":\"cmpl-1\",\"object\":\"chat.completion.chunk\",\"choices\":[{\"delta\":{\"role\":\"assistant\",\"content\":\"Hello\"},\"index\":0}]}\n\ndata: {\"id\":\"cmpl-1\",\"object\":\"chat.completion.chunk\",\"choices\":[{\"delta\":{\"content\":\" world\"},\"index\":0}]}\n\ndata: [DONE]\n\n";

    Http::fake([
        'integrate.api.nvidia.com/*' => Http::response($sseBody, 200),
    ]);

    $response = $this->postJson('/v1/chat/completions', [
        'model' => 'test-model',
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'stream' => true,
    ]);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Hello');
    expect($content)->toContain('world');
    expect($content)->toContain('[DONE]');
});
