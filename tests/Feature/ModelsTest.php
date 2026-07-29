<?php

it('returns list of models from config', function (): void {
    config()->set('relayai.entries', [
        ['provider' => 'nvidia', 'model' => 'nvidia/llama-3', 'api_key' => 'nv-test-1'],
        ['provider' => 'openrouter', 'model' => 'deepseek/deepseek-chat', 'api_key' => 'or-test-1'],
    ]);

    $response = $this->getJson('/v1/models');

    $response->assertStatus(200);
    $response->assertJsonStructure(['object', 'data']);
    $response->assertJsonCount(2, 'data');
});

it('returns empty list when no entries configured', function (): void {
    config()->set('relayai.entries', []);

    $response = $this->getJson('/v1/models');

    $response->assertStatus(200);
    $response->assertJsonCount(0, 'data');
});
