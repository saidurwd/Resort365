<?php

namespace Modules\Property\Services;

use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Modules\Property\Models\Property;
use Modules\Property\Support\AccessiblePropertyScope;

/**
 * Accessible properties: every property for holders of property.property.access-all, else the
 * ones assigned in property_user. Only active properties are offered.
 */
class PropertyAccessService implements PropertyAccess
{
    public const string ACCESS_ALL = 'property.property.access-all';

    public function accessibleProperties(Authenticatable $user): array
    {
        // Unscoped: this is what defines the scope.
        $query = Property::query()->withoutGlobalScope(AccessiblePropertyScope::class)
            ->where('status', 'active')
            ->orderBy('name');

        if (! $user instanceof Authorizable || ! $user->can(self::ACCESS_ALL)) {
            $query->whereIn('id', DB::table('property_user')->where('user_id', $user->getAuthIdentifier())->select('property_id'));
        }

        /** @var array<int, string> */
        return $query->pluck('name', 'id')->all();
    }

    /**
     * Current assignments as a set of "propertyId:userId" keys (for the access grid).
     *
     * @return array<string, bool>
     */
    public function assignments(): array
    {
        return DB::table('property_user')
            ->where('tenant_id', app(TenantContext::class)->tenantOrFail()->id)
            ->get(['property_id', 'user_id'])
            ->mapWithKeys(fn (object $row): array => [$row->property_id.':'.$row->user_id => true])
            ->all();
    }
}
