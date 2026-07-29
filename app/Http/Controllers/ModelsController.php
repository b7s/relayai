<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class ModelsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $entries = config('relayai.entries', []);
        $models = [];

        foreach ($entries as $entry) {
            $models[] = [
                'id' => $entry['provider'].'/'.$entry['model'],
                'object' => 'model',
                'created' => time(),
                'owned_by' => $entry['provider'],
            ];
        }

        return response()->json([
            'object' => 'list',
            'data' => $models,
        ]);
    }
}
