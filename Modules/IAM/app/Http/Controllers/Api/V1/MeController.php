<?php

namespace Modules\IAM\Http\Controllers\Api\V1;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * `GET /api/v1/iam/me` (Step 8.1): who the token is, what it may do and in which properties. A good first call for a new client.
 *
 * @tags IAM
 */
class MeController extends Controller
{
    /**
     * Show the token.
     *
     * The token, its user, its abilities (permission names) and the properties it may work in.
     */
    public function __invoke(Request $request, PropertyContext $properties): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var ApiToken $token */
        $token = $user->currentAccessToken();

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'expires_at' => $token->expires_at?->toIso8601String(),
            ],
            'properties' => array_map(fn (int $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($properties->accessible()), $properties->accessible()),
        ]]);
    }
}
