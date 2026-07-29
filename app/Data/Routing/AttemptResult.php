<?php

namespace App\Data\Routing;

readonly class AttemptResult
{
    public function __construct(
        public bool $success,
        public ?string $body = null,
        public ?int $statusCode = null,
        public ?string $errorMessage = null,
        public bool $retryable = true,
        public ?string $provider = null,
        public ?string $model = null,
        public ?string $apiKey = null,
    ) {}

    public function isCooldownCandidate(): bool
    {
        return ! $this->success && $this->retryable && $this->provider !== null;
    }
}
