<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\PropertyAccess;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\Core\Contracts\PlanLimits;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * Makes an API token for a user (Step 8.1). Its abilities are permission names the user holds; it may be kept to some of the user's
 * properties and given an end date. The plain token is returned once (NewAccessToken::$plainTextToken); only its hash is stored.
 */
class CreateApiToken extends Action
{
    public function __construct(private readonly PlanLimits $plan) {}

    /**
     * @param  list<string>  $abilities
     * @param  list<int>|null  $propertyIds  null: every property the user may use
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $name, array $abilities, ?array $propertyIds = null, ?CarbonInterface $expiresAt = null, ?int $createdBy = null): NewAccessToken
    {
        if (! $this->plan->hasFeature('api_access')) {
            throw ValidationException::withMessages(['name' => __('Your plan does not include API access.')]);
        }

        $abilities = array_values(array_unique($abilities));
        $held = $user->getAllPermissions()->pluck('name')->all();

        if ($abilities === []) {
            throw ValidationException::withMessages(['abilities' => __('Choose at least one thing the token may do.')]);
        }

        if (($extra = array_diff($abilities, $held)) !== []) {
            throw ValidationException::withMessages(['abilities' => __('The token can only have permissions its user holds (not :names).', ['names' => implode(', ', $extra)])]);
        }

        if ($propertyIds !== null) {
            $propertyIds = array_values(array_unique(array_map(intval(...), $propertyIds)));
            $accessible = app()->bound(PropertyAccess::class) ? array_keys(app(PropertyAccess::class)->accessibleProperties($user)) : $propertyIds;

            if ($propertyIds === [] || array_diff($propertyIds, $accessible) !== []) {
                throw ValidationException::withMessages(['property_ids' => __('Choose properties the user may work in.')]);
            }
        }

        if ($expiresAt instanceof CarbonInterface && $expiresAt->isPast()) {
            throw ValidationException::withMessages(['expires_at' => __('The end date must be in the future.')]);
        }

        $plain = $user->generateTokenString();

        /** @var ApiToken $token */
        $token = $user->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plain),
            'abilities' => $abilities,
            'property_ids' => $propertyIds,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plain);
    }
}
