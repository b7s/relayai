<?php

use App\Support\Jsonc;

it('decodes json without comments', function (): void {
    $config = Jsonc::decode('{"name":"RelayAI"}');

    expect($config)->toBe(['name' => 'RelayAI']);
});

it('strips line comments', function (): void {
    $config = Jsonc::decode(<<<'JSONC'
    {
      // a comment
      "name": "RelayAI"
    }
    JSONC);

    expect($config['name'])->toBe('RelayAI');
});

it('strips block comments', function (): void {
    $config = Jsonc::decode(<<<'JSONC'
    {
      /* multi
         line */
      "name": "RelayAI"
    }
    JSONC);

    expect($config['name'])->toBe('RelayAI');
});

it('does not strip slashes inside string literals', function (): void {
    $config = Jsonc::decode('{"url":"http://localhost:8000/v1"}');

    expect($config['url'])->toBe('http://localhost:8000/v1');
});

it('preserves trailing-slash comment-looking content inside strings', function (): void {
    $config = Jsonc::decode('{"apiKey":"{env:RELAYAI_GATEWAY_KEY} // not a comment"}');

    expect($config['apiKey'])->toBe('{env:RELAYAI_GATEWAY_KEY} // not a comment');
});

it('encodes arrays as pretty json without escaping slashes', function (): void {
    $json = Jsonc::encode(['url' => 'http://localhost:8000/v1']);

    expect($json)->toContain('http://localhost:8000/v1')
        ->not->toContain('http:\/\/');
});

it('round trips decode and encode', function (): void {
    $original = ['name' => 'RelayAI', 'url' => 'http://localhost:8000/v1'];
    $encoded = Jsonc::encode($original);
    $decoded = Jsonc::decode($encoded);

    expect($decoded)->toBe($original);
});

it('returns empty array for non-object json', function (): void {
    expect(Jsonc::decode('"just a string"'))->toBe([]);
});
