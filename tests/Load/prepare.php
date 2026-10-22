<?php

/*
| Prepares the local demo for the load test (Step 7.5, tests/Load/README.md): 25 booking users and 25 POS cashiers at Rodela, each
| with their own sign-in (the sign-in limit is per person), and the ids the scenarios need, written to tests/Load/fixture.json.
| Run after `php artisan migrate:fresh --seed`:  php tests/Load/prepare.php
| Refuses to run in production.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Models\Guest;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosTerminal;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (app()->isProduction()) {
    fwrite(STDERR, "Not in production.\n");
    exit(1);
}

$tenant = Tenant::query()->where('slug', 'rodela')->firstOrFail();

$fixture = app(TenantContext::class)->run($tenant, function () use ($tenant): array {
    $property = Property::query()->where('code', 'CXB')->firstOrFail();
    $terminal = PosTerminal::query()->where('property_id', $property->id)->where('name', 'Cashier desk')->firstOrFail();
    $people = [];

    foreach (['booking' => DefaultRole::ReservationAgent, 'pos' => DefaultRole::OutletCashier] as $kind => $role) {
        for ($n = 1; $n <= 25; $n++) {
            $email = sprintf('load-%s-%02d@rodelaresort.com', $kind, $n);
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill(['name' => sprintf('Load %s %02d', ucfirst($kind), $n), 'password' => 'password', 'status' => UserStatus::Active]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);
            DB::table('property_user')->insertOrIgnore(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'property_id' => $property->id]);

            if ($kind === 'pos') {
                DB::table('outlet_user')->insertOrIgnore(['tenant_id' => $tenant->id, 'outlet_id' => $terminal->outlet_id, 'user_id' => $user->id]);
                app(PosPins::class)->set($user->id, sprintf('7%03d', $n));
            }

            $people[$kind][] = ['id' => $user->id, 'email' => $email, 'pin' => $kind === 'pos' ? sprintf('7%03d', $n) : null];
        }
    }

    // Plain dishes on sale at the outlet all day: no variant or modifier to choose, not 86'd, no schedule.
    $onSale = OutletMenuItem::query()->where('outlet_id', $terminal->outlet_id)->where('is_available', true)->whereNull('schedule_ids')->whereNull('menu_item_variant_id')->pluck('menu_item_id');
    $items = MenuItem::query()->whereKey($onSale)->where('kind', 'dish')->where('is_active', true)->whereDoesntHave('variants')->whereDoesntHave('modifierGroups')->limit(12)->pluck('id')->all();

    $guests = Guest::query()->where('is_blacklisted', false)->orderBy('id')->limit(200)->pluck('id')->all();

    return ['people' => $people, 'items' => $items, 'guests' => $guests, 'device_token' => 'DEMO-CASH-DESK-0001'];
});

file_put_contents(__DIR__.'/fixture.json', json_encode($fixture, JSON_PRETTY_PRINT));
echo 'Wrote tests/Load/fixture.json: '.count($fixture['people']['booking']).' booking users, '.count($fixture['people']['pos']).' cashiers, '.count($fixture['items']).' menu items, '.count($fixture['guests'])." guests.\n";
