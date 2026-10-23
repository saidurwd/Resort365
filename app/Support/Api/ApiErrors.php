<?php

namespace App\Support\Api;

use App\Support\Tenancy\PlanLimitReached;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * One error shape for every API response (Step 8.1, ARCHITECTURE §9.6): `{"message": …, "code": …}`, plus `errors` for validation
 * and any details the error carries. Registered in bootstrap/app.php for `api/*` requests.
 */
final class ApiErrors
{
    /** Codes for plain HTTP errors. */
    private const array CODES = [
        400 => 'bad_request', 401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 405 => 'method_not_allowed',
        409 => 'conflict', 413 => 'payload_too_large', 419 => 'session_expired', 422 => 'unprocessable', 429 => 'too_many_requests',
        503 => 'service_unavailable',
    ];

    /**
     * The API's answer to the error, or null to leave it to Laravel (an unexpected error while debugging, with its trace).
     */
    public static function render(Throwable $exception): ?JsonResponse
    {
        return match (true) {
            $exception instanceof ValidationException => self::body(422, 'validation_failed', $exception->getMessage(), ['errors' => $exception->errors()]),
            $exception instanceof AuthenticationException => self::body(401, 'unauthenticated', __('A valid API token is required.')),
            $exception instanceof AuthorizationException => self::body(403, 'forbidden', $exception->getMessage() !== '' && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : __('This token may not do that.')),
            $exception instanceof ModelNotFoundException => self::body(404, 'not_found', __('Not found.')),
            $exception instanceof PlanLimitReached => self::body(422, 'plan_limit', $exception->getMessage(), ['limit' => $exception->limit]),
            $exception instanceof ApiError => self::body($exception->apiStatus(), $exception->apiCode(), $exception->getMessage(), $exception->apiDetails()),
            $exception instanceof ThrottleRequestsException => self::body(429, 'too_many_requests', __('Too many requests. Try again shortly.'), headers: $exception->getHeaders()),
            $exception instanceof HttpExceptionInterface => self::http($exception),
            (bool) config('app.debug') => null,
            default => self::body(500, 'server_error', __('Something went wrong on our side.')),
        };
    }

    private static function http(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : __('Not found.');

        return self::body($status, self::CODES[$status] ?? ($status >= 500 ? 'server_error' : 'http_error'), $status === 404 ? __('Not found.') : $message, headers: $exception->getHeaders());
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string>  $headers
     */
    private static function body(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'code' => $code, ...$details], $status, $headers);
    }
}
