<?php

namespace App\Actions\Routing;

use App\Data\Routing\AttemptResult;
use App\Models\ProviderFailure;
use Illuminate\Support\Facades\DB;

final readonly class RecordFailure
{
    public function __invoke(AttemptResult $result): void
    {
        $mask = strlen((string) $result->apiKey) > 8
            ? substr((string) $result->apiKey, 0, 4).'...'.substr((string) $result->apiKey, -4)
            : substr((string) $result->apiKey, 0, 4).'...';

        DB::transaction(function () use ($result, $mask): void {
            ProviderFailure::create([
                'provider' => $result->provider,
                'model' => $result->model,
                'api_key_mask' => $mask,
                'error_type' => $this->classifyError($result),
                'error_message' => $result->errorMessage,
                'failed_at' => now(),
            ]);
        });
    }

    private function classifyError(AttemptResult $result): string
    {
        return match (true) {
            $result->statusCode === 429 => 'rate_limit',
            $result->statusCode === 401, $result->statusCode === 403 => 'auth',
            $result->statusCode === 400 => 'bad_request',
            $result->statusCode === 0 || $result->statusCode === null => 'network',
            $result->statusCode >= 500 => 'server_error',
            default => 'unknown',
        };
    }
}
