<?php

use App\Http\Controllers\ChatCompletionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ModelsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('chat/completions', ChatCompletionController::class);
    Route::get('models', ModelsController::class);
    Route::get('health', [HealthController::class, 'index']);
});

Route::get('up', [HealthController::class, 'up']);
