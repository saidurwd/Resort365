<?php

namespace Modules\Restaurant\Broadcasting;

use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Modules\Restaurant\Auth\StationDisplay;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\OutletAccess;

/**
 * The restaurant's private channels (ARCHITECTURE AD-17, Step 3.5). Every name starts with the tenant id
 * and a channel is only granted on that tenant's own subdomain, so screens never hear another tenant:
 *  - tenant.{t}.outlet.{o}: the outlet's POS screens (tables, ready dishes, 86) — staff of the outlet
 *    with restaurant.pos.use.
 *  - tenant.{t}.kitchen.{s}: a station's kitchen display — that station's display device, or a person
 *    with restaurant.kds.use who works in the station's outlet.
 */
final class RestaurantChannels
{
    public static function outlet(int $tenantId, int $outletId): string
    {
        return 'tenant.'.$tenantId.'.outlet.'.$outletId;
    }

    public static function kitchen(int $tenantId, int $stationId): string
    {
        return 'tenant.'.$tenantId.'.kitchen.'.$stationId;
    }

    public static function register(): void
    {
        Broadcast::channel('tenant.{tenantId}.outlet.{outletId}', function (Authenticatable $user, string $tenantId, string $outletId): bool {
            if (! self::currentTenant($tenantId) || $user instanceof StationDisplay || ! $user instanceof Authorizable) {
                return false;
            }

            return $user->can('restaurant.pos.use') && Outlet::query()->whereKey((int) $outletId)->exists()
                && app(OutletAccess::class)->canUse((int) $user->getAuthIdentifier(), (int) $outletId);
        }, ['guards' => ['web']]);

        Broadcast::channel('tenant.{tenantId}.kitchen.{stationId}', function (Authenticatable $user, string $tenantId, string $stationId): bool {
            if (! self::currentTenant($tenantId)) {
                return false;
            }

            if ($user instanceof StationDisplay) {
                return $user->station->id === (int) $stationId && $user->station->tenant_id === (int) $tenantId;
            }

            $station = KitchenStation::query()->find((int) $stationId);

            return $user instanceof Authorizable && $station instanceof KitchenStation && $user->can('restaurant.kds.use')
                && app(OutletAccess::class)->canUse((int) $user->getAuthIdentifier(), $station->outlet_id);
        }, ['guards' => ['kds', 'web']]);
    }

    private static function currentTenant(string $tenantId): bool
    {
        return ctype_digit($tenantId) && app(TenantContext::class)->id() === (int) $tenantId;
    }
}
