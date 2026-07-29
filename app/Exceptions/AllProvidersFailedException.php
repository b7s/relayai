<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class AllProvidersFailedException extends Exception
{
    public function __construct(
        string $message = 'All AI providers failed',
        int $code = 503,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'type' => 'https://tools.ietf.org/html/rfc9457',
            'title' => 'All Providers Failed',
            'status' => $this->getCode(),
            'detail' => $this->getMessage(),
        ], $this->getCode());
    }
}
