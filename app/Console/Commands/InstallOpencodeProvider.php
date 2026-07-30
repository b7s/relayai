<?php

namespace App\Console\Commands;

use App\Support\Jsonc;
use Illuminate\Console\Command;

final class InstallOpencodeProvider extends Command
{
    private const string DEFAULT_SCHEMA = 'https://opencode.ai/config.json';

    private const string DEFAULT_NPM = '@ai-sdk/openai-compatible';

    private const string DEFAULT_NAME = 'RelayAI';

    protected $signature = 'relayai:install-opencode {--path= : Absolute path to opencode.jsonc (default: ~/.config/opencode/opencode.jsonc)} {--view : Only display the provider config without saving}';

    protected $description = 'Add or update the relayai provider in the opencode.jsonc config file.';

    public function handle(): int
    {
        $path = $this->resolvePath();
        $config = $this->read($path);

        $baseURL = rtrim(config()->string('app.url', 'http://localhost'), '/').'/v1';
        $apiKey = config()->string('relayai.gateway_key', '');
        $models = $this->getModelsFromEntries();

        $providers = is_array($config['provider'] ?? null) ? $config['provider'] : [];
        $existing = is_array($providers['relayai'] ?? null) ? $providers['relayai'] : [];

        $provider = $this->buildProvider($existing, $baseURL, $apiKey, $models);

        if ($this->option('view')) {
            $json = Jsonc::encode(['provider' => ['relayai' => $provider]]);
            // Remove outer braces for cleaner output
            $json = trim($json);
            $json = substr($json, 1, -1);
            $this->line($json);
            return self::SUCCESS;
        }

        $providers['relayai'] = $provider;
        $config['provider'] = $providers;

        $this->write($path, $config);

        $this->info("Configured 'relayai' provider in: {$path}");
        $this->line("  baseURL: {$baseURL}");
        $this->line('  apiKey: '.($apiKey !== '' ? $this->maskKey($apiKey) : '(empty)'));
        $this->line('  models: '.implode(', ', array_keys($models)));

        return self::SUCCESS;
    }

    private function resolvePath(): string
    {
        $explicit = $this->normalize(trim((string) $this->option('path')));

        if ($explicit !== '') {
            return $explicit;
        }

        return $this->normalize($this->defaultPath());
    }

    private function defaultPath(): string
    {
        return $this->home().'/.config/opencode/opencode.jsonc';
    }

    private function home(): string
    {
        return (string) (getenv('HOME') ?: getenv('USERPROFILE') ?: sys_get_temp_dir());
    }

    private function normalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if ($path[0] === '~') {
            return $this->home().substr($path, 1);
        }

        return $path;
    }

    /** @return array<int|string, mixed> */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            return [
                '$schema' => self::DEFAULT_SCHEMA,
                'provider' => [],
            ];
        }

        $config = Jsonc::decode((string) file_get_contents($path));

        $config['provider'] = is_array($config['provider'] ?? null) ? $config['provider'] : [];

        return $config;
    }

    /** @param  array<int|string, mixed>  $config */
    private function write(string $path, array $config): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents($path, Jsonc::encode($config));
    }

    /**
     * @param  array<int|string, mixed>  $existing
     * @param  array<string, string>  $models
     * @return array<int|string, mixed>
     */
    private function buildProvider(array $existing, string $baseURL, string $apiKey, array $models): array
    {
        $provider = $existing;
        $provider['npm'] ??= self::DEFAULT_NPM;
        $provider['name'] ??= self::DEFAULT_NAME;

        $options = is_array($provider['options'] ?? null) ? $provider['options'] : [];
        $options['baseURL'] = $baseURL;
        $options['apiKey'] = $apiKey;
        $provider['options'] = $options;

        if ($models !== []) {
            $existingModels = is_array($provider['models'] ?? null) ? $provider['models'] : [];
            $provider['models'] = $models + $existingModels;
        }

        return $provider;
    }

    /** @return array<string, string> */
    private function getModelsFromEntries(): array
    {
        /** @var array<int, array{provider: string, model: string, api_key?: string}> $entries */
        $entries = config('relayai.entries', []);

        $models = [];
        foreach ($entries as $entry) {
            $models[$entry['model']] = 'RelayAI';
        }

        return $models;
    }

    private function maskKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 4) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 4).str_repeat('*', 4).'...';
    }
}
