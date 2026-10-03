<?php

namespace Modules\Property\Tests\Support;

use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;

/**
 * Shared setup for the Property module's feature tests: tenant "sunrise" with properties CXB and SYL.
 */
final class PropertySetup
{
    public static function tenant(): Tenant
    {
        $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));

        app(TenantContext::class)->run($tenant, function (): void {
            Property::factory()->create(['code' => 'CXB', 'name' => "Sunrise Cox's Bazar"]);
            Property::factory()->create(['code' => 'SYL', 'name' => 'Sunrise Sylhet']);
        });

        return $tenant;
    }

    /**
     * Run in the tenant without a signed-in user's property restriction (the last test request's
     * restriction lingers in the container).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        app(PropertyContext::class)->clear();

        return app(TenantContext::class)->run(tenant('sunrise'), $callback);
    }

    public static function property(string $code): Property
    {
        return self::run(fn (): Property => Property::query()->where('code', $code)->firstOrFail());
    }

    /**
     * A user with the role, assigned to the given properties.
     */
    public static function user(DefaultRole $role, string ...$codes): User
    {
        $user = tenantUserAs(tenant('sunrise'), $role);

        foreach ($codes as $code) {
            DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'property_id' => self::property($code)->id, 'user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * Session data that makes the property the current one.
     *
     * @return array<string, int>
     */
    public static function current(string $code): array
    {
        return [SetCurrentProperty::SESSION_KEY => self::property($code)->id];
    }
}
