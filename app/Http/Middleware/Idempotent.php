<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiException;
use App\Support\Tenancy\TenantCache;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `idempotent` on API writes that must not happen twice (Step 8.1, e.g. creating a booking). With an `Idempotency-Key` header the
 * first successful answer is kept for a day per tenant and token, and a retry with the same key and body gets it again (header
 * `Idempotent-Replayed: true`) instead of a second booking. A refusal is not kept, so the client may fix the request and retry. The
 * same key with another body is a 409 `idempotency_conflict`. Without the header the request simply runs.
 */
class Idempotent
{
    public const string HEADER = 'Idempotency-Key';

    private const int TTL = 86_400;

    public function __construct(private readonly TenantCache $cache) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header(self::HEADER));

        if ($key === '') {
            return $next($request);
        }

        if (strlen($key) > 100) {
            throw new ApiException(422, 'validation_failed', __('The Idempotency-Key header may be at most 100 characters.'));
        }

        $token = $request->user()?->currentAccessToken();
        $scope = 'idempotency:'.($token instanceof PersonalAccessToken ? $token->getKey() : 'none').':'.hash('sha256', $key);
        $fingerprint = hash('sha256', $request->method().' '.$request->path().' '.json_encode($request->all()));

        return Cache::lock($this->cache->key($scope.':lock'), 30)->block(10, function () use ($request, $next, $scope, $fingerprint): Response {
            $stored = $this->cache->get($scope);

            if (is_array($stored)) {
                if ($stored['fingerprint'] !== $fingerprint) {
                    throw new ApiException(409, 'idempotency_conflict', __('This Idempotency-Key was already used for a different request.'));
                }

                return new JsonResponse($stored['body'], (int) $stored['status'], ['Idempotent-Replayed' => 'true']);
            }

            $response = $next($request);

            if ($response instanceof JsonResponse && $response->isSuccessful()) {
                $this->cache->put($scope, ['fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => $response->getData(true)], self::TTL);
            }

            return $response;
        });
    }
}
