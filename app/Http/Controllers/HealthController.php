<?php

namespace App\Http\Controllers;

use App\Enums\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $entries */
        $entries = config('relayai.entries', []);
        $providers = [];

        foreach ($entries as $entry) {
            $provider = isset($entry['provider']) && is_string($entry['provider']) ? $entry['provider'] : '';
            $model = isset($entry['model']) && is_string($entry['model']) ? $entry['model'] : '';
            $key = "{$provider}:{$model}";

            if (! isset($providers[$key])) {
                $providers[$key] = [
                    'provider' => $provider,
                    'model' => $model,
                    'reachable' => $this->checkProvider($provider),
                ];
            }
        }

        $allReachable = ! empty($providers) && collect($providers)->every(fn ($p) => $p['reachable']);

        return response()->json([
            'status' => $allReachable ? 'ok' : 'degraded',
            'providers' => array_values($providers),
        ]);
    }

    public function up(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    private function checkProvider(string $providerName): bool
    {
        return Cache::remember("provider_health:{$providerName}", 60, function () use ($providerName): bool {
            try {
                $provider = Provider::fromName($providerName);
                $response = Http::timeout(5)->head(rtrim($provider->baseUrl(), '/'));

                return $response->status() < 500;
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
