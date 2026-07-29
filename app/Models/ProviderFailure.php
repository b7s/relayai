<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon $failed_at
 */
class ProviderFailure extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'api_key_mask',
        'error_type',
        'error_message',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRecentFailures(
        Builder $query,
        string $provider,
        string $model,
        string $apiKeyMask,
        int $windowMinutes
    ): Builder {
        return $query
            ->where('provider', $provider)
            ->where('model', $model)
            ->where('api_key_mask', $apiKeyMask)
            ->where('failed_at', '>=', now()->subMinutes($windowMinutes));
    }
}
