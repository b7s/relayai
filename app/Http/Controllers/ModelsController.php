<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ModelsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $entries */
        $entries = config('relayai.entries', []);
        $models = [];

        foreach ($entries as $entry) {
            $provider = isset($entry['provider']) && is_string($entry['provider']) ? $entry['provider'] : '';
            $model = isset($entry['model']) && is_string($entry['model']) ? $entry['model'] : '';
            $models[] = [
                'id' => $provider.'/'.$model,
                'object' => 'model',
                'created' => time(),
                'owned_by' => $provider,
            ];
        }

        return response()->json([
            'object' => 'list',
            'data' => $models,
        ]);
    }
}
