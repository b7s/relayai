<?php

use App\Http\Controllers\ChatCompletionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ModelsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('chat/completions', ChatCompletionController::class)->name('chat.completions');
    Route::get('models', ModelsController::class)->name('models');
    Route::get('health', [HealthController::class, 'index'])->name('health');
});

Route::get('up', [HealthController::class, 'up'])->name('up');
