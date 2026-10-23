<?php

namespace App\Support\Api;

/**
 * An exception that knows how the REST API answers it (Step 8.1, ARCHITECTURE §9.6): an HTTP status and a stable error code
 * (snake_case). Modules implement it on the exceptions their Actions throw, e.g. a booking clash is 409 `room_no_longer_available`.
 */
interface ApiError
{
    public function apiStatus(): int;

    public function apiCode(): string;

    /**
     * Extra fields for the error body, next to `message` and `code`.
     *
     * @return array<string, mixed>
     */
    public function apiDetails(): array;
}
