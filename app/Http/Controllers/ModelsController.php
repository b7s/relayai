<?php

namespace App\Http\Controllers;

use App\Data\Routing\ConfigEntry;
use Illuminate\Http\JsonResponse;

final class ModelsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $models = [];

        foreach (ConfigEntry::all() as $entry) {
            $models[] = [
                'id' => $entry->provider.'/'.$entry->model,
                'object' => 'model',
                'created' => time(),
                'owned_by' => $entry->provider,
            ];
        }

        return response()->json([
            'object' => 'list',
            'data' => $models,
        ]);
    }
}
