<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * REST API demo (Step 8.1): Rodela's reservations agent has the token `DEMO-API-RODELA-0001` for availability, quotes, bookings,
 * guests and folios (only what the role holds), so the API can be tried at once:
 *
 *     curl -H "Authorization: Bearer DEMO-API-RODELA-0001" "http://rodela.resort365.test/api/v1/iam/me"
 *
 * Green Valley's plan (Professional) has no API access, so a token there is refused with `api_not_in_plan`.
 */
final class DemoApi
{
    public const string TOKEN = 'DEMO-API-RODELA-0001';

    private const array ABILITIES = [
        'reservation.availability.view', 'reservation.quote.view', 'reservation.quote.create', 'reservation.booking.view',
        'reservation.booking.create', 'reservation.booking.cancel', 'guest.guest.view', 'guest.guest.create', 'guest.guest.update',
        'billing.folio.view',
    ];

    public static function seed(Tenant $tenant, string $email): void
    {
        app(TenantContext::class)->run($tenant, function () use ($email): void {
            $user = User::query()->where('email', $email)->firstOrFail();
            $held = $user->getAllPermissions()->pluck('name')->all();

            ApiToken::query()->where('token', hash('sha256', self::TOKEN))->delete();
            $user->tokens()->create([
                'name' => 'Demo booking website',
                'token' => hash('sha256', self::TOKEN),
                'abilities' => array_values(array_intersect(self::ABILITIES, $held)),
            ]);
        });
    }
}
