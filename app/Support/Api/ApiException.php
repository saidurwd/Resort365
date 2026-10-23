<?php

namespace App\Support\Api;

use RuntimeException;

/**
 * A refusal the API reports as it is: status, code, message and optional details (e.g. 403 `api_not_in_plan`).
 */
class ApiException extends RuntimeException implements ApiError
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(private readonly int $status, private readonly string $errorCode, string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function apiStatus(): int
    {
        return $this->status;
    }

    public function apiCode(): string
    {
        return $this->errorCode;
    }

    public function apiDetails(): array
    {
        return $this->details;
    }
}
