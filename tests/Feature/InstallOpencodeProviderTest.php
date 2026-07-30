<?php

use App\Support\Jsonc;

it('updates the relayai provider options from app config', function (): void {
    config()->set('app.url', 'http://relayai.test');
    config()->set('relayai.gateway_key', 'super-secret-key');

    $path = tempnam(sys_get_temp_dir(), 'opencode');
    file_put_contents($path, <<<'JSONC'
    {
      "$schema": "https://opencode.ai/config.json",
      "provider": {
        "relayai": {
          "npm": "@ai-sdk/openai-compatible",
          "name": "Old Name",
          "options": {
            "baseURL": "http://old.example/v1",
            "apiKey": "old-key"
          },
          "models": {
            "z-ai/glm-5.2": { "name": "GLM 5.2" }
          }
        }
      }
    }
    JSONC);

    $this->artisan('relayai:install-opencode', ['--path' => $path])
        ->assertSuccessful()
        ->expectsOutputToContain('baseURL: http://relayai.test/v1');

    /** @var array<string, mixed> $config */
    $config = Jsonc::decode((string) file_get_contents($path));

    expect($config['provider']['relayai']['options']['baseURL'])->toBe('http://relayai.test/v1')
        ->and($config['provider']['relayai']['options']['apiKey'])->toBe('super-secret-key')
        ->and($config['provider']['relayai']['models'])->toHaveKey('z-ai/glm-5.2')
        ->and($config['provider']['relayai']['name'])->toBe('Old Name')
        ->and($config['$schema'])->toBe('https://opencode.ai/config.json');

    @unlink($path);
});

it('creates the provider and file when it does not exist', function (): void {
    config()->set('app.url', 'http://relayai.test');
    config()->set('relayai.gateway_key', 'secret');

    $path = sys_get_temp_dir().'/relayai_test_'.uniqid('oc', true).'/nested/opencode.jsonc';

    $this->artisan('relayai:install-opencode', ['--path' => $path])->assertSuccessful();

    expect(is_file($path))->toBeTrue();

    /** @var array<string, mixed> $config */
    $config = Jsonc::decode((string) file_get_contents($path));

    expect($config['$schema'])->toBe('https://opencode.ai/config.json')
        ->and($config['provider']['relayai']['npm'])->toBe('@ai-sdk/openai-compatible')
        ->and($config['provider']['relayai']['name'])->toBe('RelayAI')
        ->and($config['provider']['relayai']['options']['baseURL'])->toBe('http://relayai.test/v1')
        ->and($config['provider']['relayai']['options']['apiKey'])->toBe('secret');

    @unlink($path);
    @rmdir(dirname($path));
    @rmdir(dirname($path, 2));
});

it('handles jsonc comments in the existing file', function (): void {
    config()->set('app.url', 'http://relayai.test');
    config()->set('relayai.gateway_key', 'secret');

    $path = tempnam(sys_get_temp_dir(), 'opencode');
    file_put_contents($path, <<<'JSONC'
    {
      // top-level comment
      "lsp": {},
      "provider": {
        "relayai": {
          "npm": "@ai-sdk/openai-compatible", // inline
          "options": { "baseURL": "http://old/v1" }
        }
      }
    }
    JSONC);

    $this->artisan('relayai:install-opencode', ['--path' => $path])->assertSuccessful();

    /** @var array<string, mixed> $config */
    $config = Jsonc::decode((string) file_get_contents($path));

    expect($config['lsp'])->toBe([])
        ->and($config['provider']['relayai']['options']['baseURL'])->toBe('http://relayai.test/v1');

    @unlink($path);
});
