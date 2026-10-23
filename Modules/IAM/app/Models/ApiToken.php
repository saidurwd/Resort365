<?php

namespace Modules\IAM\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\IAM\Database\Factories\ApiTokenFactory;

/**
 * An API token (Step 8.1, ARCHITECTURE §9.6): Sanctum's personal access token, owned by the tenant, so a token is only ever found
 * on its own tenant's subdomain. Its abilities are permission names (a part of its user's); `property_ids` keeps it to some of
 * the user's properties (null = all of them). Only the SHA-256 hash of the token is stored.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $name
 * @property string $token
 * @property list<string>|null $abilities
 * @property list<int>|null $property_ids
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon $created_at
 */
#[UseFactory(ApiTokenFactory::class)]
#[Fillable(['name', 'token', 'abilities', 'property_ids', 'expires_at', 'created_by'])]
#[Table(name: 'personal_access_tokens')]
class ApiToken extends PersonalAccessToken
{
    use BelongsToTenant;

    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'abilities' => 'json',
            'property_ids' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the token may be used for the property (null property_ids: every property of its user).
     */
    public function allowsProperty(int $propertyId): bool
    {
        return $this->property_ids === null || in_array($propertyId, array_map(intval(...), $this->property_ids), true);
    }

    /**
     * Using the token is not a change worth an audit entry; creating and revoking it are.
     *
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return ['last_used_at', 'last_used_ip'];
    }
}
