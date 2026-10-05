<?php

/*
| POS test helpers (Steps 3.3–3.4): a terminal, staff with PINs, the device cookie and PIN sign-in.
*/

use App\Support\Authorization\DefaultRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Models\User;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosDevice;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\post;
use function Pest\Laravel\withCookie;
use function Pest\Laravel\withCredentials;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

/**
 * The Main Restaurant with a cashier tablet; returns the terminal and its plain device token.
 *
 * @return array{PosTerminal, string}
 */
function posTerminal(): array
{
    return booking(function (): array {
        $outlet = Outlet::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'MR', 'name' => 'Main Restaurant']);

        return RegisterTerminal::make()->handle($outlet, 'Cashier desk');
    });
}

/**
 * A member of staff with a POS PIN, working in the outlet (unless $outlet is false) and the property.
 */
function posStaff(DefaultRole $role, string $pin, ?PosTerminal $terminal, bool $outlet = true): User
{
    $user = tenantUserAs(tenant('sunrise'), $role);
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => bookingIds()['property']]);

    if ($outlet && $terminal instanceof PosTerminal) {
        DB::table('outlet_user')->insert(['tenant_id' => $user->tenant_id, 'outlet_id' => $terminal->outlet_id, 'user_id' => $user->id]);
    }

    booking(fn () => app(PosPins::class)->set($user->id, $pin));

    return $user;
}

function onDevice(PosTerminal $terminal): void
{
    $fresh = booking(fn (): PosTerminal => PosTerminal::query()->findOrFail($terminal->id));
    withCookie(PosDevice::COOKIE, $fresh->id.'|'.$fresh->device_token);
    withCredentials(); // JSON requests send cookies only with credentials
}

/**
 * @return TestResponse<Response>
 */
function pinSignIn(User $user, string $pin): TestResponse
{
    return post(tenantUrl('sunrise', '/pos/sign-in'), ['user_id' => $user->id, 'pin' => $pin]);
}
