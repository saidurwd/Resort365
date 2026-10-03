<?php

namespace Modules\Guest\Tests\Support;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Models\Guest;

/**
 * Shared setup for the Guest module's feature tests (tenant "sunrise").
 */
final class GuestSetup
{
    public static function tenant(): Tenant
    {
        DB::table('countries')->insertOrIgnore([
            ['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh'],
            ['code' => 'GB', 'iso3' => 'GBR', 'numeric_code' => '826', 'name' => 'United Kingdom'],
        ]);

        return withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Closure $callback): mixed
    {
        return app(TenantContext::class)->run(tenant('sunrise'), $callback);
    }

    /**
     * A guest saved the way the app saves them (normalised phone, ID hash).
     *
     * @param  array<string, mixed>  $data
     */
    public static function guest(array $data): Guest
    {
        return self::run(fn (): Guest => SaveGuest::make()->handle(null, ['vip_level' => 'none', ...$data]));
    }

    public static function fresh(Guest $guest): Guest
    {
        return self::run(fn (): Guest => Guest::withTrashed()->findOrFail($guest->id));
    }
}
