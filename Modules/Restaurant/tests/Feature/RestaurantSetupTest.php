<?php

/*
| Restaurant setup (Step 3.1). "Done when": outlets with stations; the floor plan saves table
| positions.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\TaxCategory;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Services\OutletAccess;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function newOutlet(array $overrides = []): Outlet
{
    post(tenantUrl('sunrise', '/restaurant/outlets'), [...['code' => 'MR', 'name' => 'Main Restaurant', 'type' => 'restaurant', 'bill_prefix' => 'MR', 'prices_include_tax' => '0',
        'is_active' => '1', 'opening_hours' => ['mon' => ['open' => '07:00', 'close' => '23:00']]], ...$overrides])->assertSessionHasNoErrors();

    return booking(fn (): Outlet => Outlet::query()->latest('id')->firstOrFail());
}

function printerOf(string $type): Printer
{
    return booking(fn (): Printer => Printer::factory()->create(['property_id' => bookingIds()['property'], 'type' => $type, 'name' => ucfirst($type).' printer']));
}

it('creates an outlet with its hours and shows its setup page', function (): void {
    staffUser(DefaultRole::FnbManager);
    $outlet = newOutlet(['default_tax_category_id' => booking(fn () => TaxCategory::query()->where('code', 'ROOM')->value('id'))]);

    expect([$outlet->code, $outlet->type->value, $outlet->opening_hours['mon'] ?? null])->toBe(['MR', 'restaurant', ['open' => '07:00', 'close' => '23:00']]);
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertSeeHtml('data-outlet-details')->assertSee('Mon 07:00–23:00');
    get(tenantUrl('sunrise', '/restaurant/outlets'))->assertOk()->assertSeeHtml('data-outlet="MR"');

    post(tenantUrl('sunrise', '/restaurant/outlets'), ['code' => 'mr', 'name' => 'Copy', 'type' => 'bar', 'bill_prefix' => 'X'])->assertSessionHasErrors('code');
    post(tenantUrl('sunrise', '/restaurant/outlets'), ['code' => 'PB', 'name' => 'Pool Bar', 'type' => 'disco', 'bill_prefix' => 'PB',
        'opening_hours' => ['mon' => ['open' => '11:00']]])->assertSessionHasErrors(['type', 'opening_hours.mon.close']);

    put(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"), ['code' => 'MR', 'name' => 'The Main Restaurant', 'type' => 'restaurant', 'bill_prefix' => 'MR', 'is_active' => '0'])
        ->assertRedirect(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"));
    expect(booking(fn () => [$outlet->fresh()?->name, $outlet->fresh()?->is_active]))->toBe(['The Main Restaurant', false]);
});

it('gives each station a kitchen display, a kitchen-ticket printer or both', function (): void {
    staffUser(DefaultRole::FnbManager);
    $outlet = newOutlet();
    $kot = printerOf('kot');
    $receipt = printerOf('receipt');
    $url = tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/stations");

    post($url, ['name' => 'Grill', 'output' => 'display'])->assertSessionHas('success');
    post($url, ['name' => 'Hot kitchen', 'output' => 'both'])->assertSessionHas('error');
    post($url, ['name' => 'Hot kitchen', 'output' => 'printer', 'printer_id' => $receipt->id])->assertSessionHas('error');
    post($url, ['name' => 'Hot kitchen', 'output' => 'both', 'printer_id' => $kot->id])->assertSessionHas('success');
    post($url, ['name' => 'Grill', 'output' => 'display'])->assertSessionHasErrors('name');

    $station = booking(fn (): KitchenStation => KitchenStation::query()->where('name', 'Grill')->sole());
    put("{$url}/{$station->id}", ['name' => 'Grill', 'output' => 'display', 'printer_id' => $kot->id])->assertSessionHas('success');
    expect(booking(fn () => $station->fresh()?->printer_id))->toBeNull();

    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertSeeHtml('data-station="Hot kitchen"');
    delete("{$url}/{$station->id}")->assertSessionHas('success');
    expect(booking(fn (): int => KitchenStation::query()->count()))->toBe(1);
});

it('registers terminals with a device token shown once and stored hashed', function (): void {
    staffUser(DefaultRole::FnbManager);
    $outlet = newOutlet();

    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/terminals"), ['name' => 'Captain tablet'])->assertSessionHas('terminal_token');
    $token = session('terminal_token')['token'];
    $terminal = booking(fn (): PosTerminal => PosTerminal::query()->sole());

    expect($terminal->device_token)->toBe(hash('sha256', $token))
        ->and($terminal->device_token === $token)->toBeFalse()
        ->and(array_key_exists('device_token', $terminal->toArray()))->toBeFalse();
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertSee($token);
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertDontSee($token);

    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/terminals/{$terminal->id}/token"))->assertSessionHas('terminal_token');
    $renewed = booking(fn (): ?string => $terminal->fresh()?->device_token);
    expect($renewed)->toBe(hash('sha256', session('terminal_token')['token']))
        ->and($renewed === hash('sha256', $token))->toBeFalse();

    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/terminals"), ['name' => 'Till', 'receipt_printer_id' => printerOf('kot')->id])->assertSessionHasErrors('receipt_printer_id');
});

it('lays out areas and tables and saves the positions dragged on the floor plan', function (): void {
    staffUser(DefaultRole::FnbManager);
    $outlet = newOutlet();
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas"), ['name' => 'Indoor'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas"), ['name' => 'Terrace'])->assertSessionHas('success');
    [$indoor, $terrace] = booking(fn () => DiningArea::query()->orderBy('id')->get()->all());

    foreach ([['T1', 2, 'square'], ['T2', 4, 'round'], ['T3', 8, 'rectangle']] as [$number, $seats, $shape]) {
        post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/tables"), ['dining_area_id' => $indoor->id, 'number' => $number, 'seats' => $seats, 'shape' => $shape])->assertSessionHas('success');
    }
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/tables"), ['dining_area_id' => $terrace->id, 'number' => 't1', 'seats' => 2, 'shape' => 'square'])->assertSessionHasErrors('number');

    $tables = booking(fn () => DiningTable::query()->orderBy('id')->get());
    expect($tables->map(fn (DiningTable $table): array => [$table->pos_x, $table->pos_y])->unique()->count())->toBe(3);

    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertSeeHtml('data-number="T2"');

    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas/{$indoor->id}/positions"), ['positions' => [
        ['id' => $tables[0]->id, 'x' => 404, 'y' => 236], ['id' => $tables[1]->id, 'x' => 1000, 'y' => 600],
    ]])->assertOk()->assertJson(['ok' => true, 'message' => 'Floor plan saved: 2 tables moved.']);

    expect(booking(fn (): array => DiningTable::query()->orderBy('id')->get()->map(fn (DiningTable $table): array => [$table->pos_x, $table->pos_y])->all()))
        ->toBe([[400, 240], [930, 530], [$tables[2]->pos_x, $tables[2]->pos_y]]);

    // A table of another area is refused, and nothing moves.
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas/{$terrace->id}/positions"), ['positions' => [['id' => $tables[0]->id, 'x' => 0, 'y' => 0]]])
        ->assertUnprocessable()->assertJsonPath('ok', false);
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas/{$indoor->id}/positions"), ['positions' => [['id' => $tables[0]->id, 'x' => 2000, 'y' => 0]]])
        ->assertUnprocessable()->assertJsonValidationErrors('positions.0.x');

    // Moving a table to another area places it there; an area with tables cannot be removed.
    put(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/tables/{$tables[2]->id}"), ['dining_area_id' => $terrace->id, 'number' => 'T3', 'seats' => 8, 'shape' => 'rectangle', 'is_active' => '1'])
        ->assertSessionHas('success');
    expect(booking(fn () => [$tables[2]->fresh()?->dining_area_id, $tables[2]->fresh()?->pos_x, $tables[2]->fresh()?->pos_y]))->toBe([$terrace->id, 20, 20]);
    delete(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas/{$terrace->id}"))->assertSessionHas('error');
    delete(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/tables/{$tables[2]->id}"))->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas/{$terrace->id}"))->assertSessionHas('success');
});

it('keeps printers per property and needs an address for network printers', function (): void {
    staffUser(DefaultRole::FnbManager);

    post(tenantUrl('sunrise', '/restaurant/printers'), ['name' => 'Kitchen', 'type' => 'kot', 'connection' => 'network', 'paper_width_mm' => 80])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/restaurant/printers'), ['name' => 'Kitchen', 'type' => 'kot', 'connection' => 'network', 'address' => '192.168.1.50:9100', 'paper_width_mm' => 80])->assertSessionHas('success');
    post(tenantUrl('sunrise', '/restaurant/printers'), ['name' => 'Receipt', 'type' => 'receipt', 'connection' => 'browser', 'paper_width_mm' => 57])->assertSessionHasErrors('paper_width_mm');

    get(tenantUrl('sunrise', '/restaurant/printers'))->assertOk()->assertSee('192.168.1.50:9100');
    $printer = booking(fn (): Printer => Printer::query()->sole());
    delete(tenantUrl('sunrise', "/restaurant/printers/{$printer->id}"))->assertSessionHas('success');
});

it('assigns staff to outlets; the general manager works in every outlet', function (): void {
    staffUser(DefaultRole::FnbManager);
    $main = newOutlet();
    $bar = newOutlet(['code' => 'PB', 'name' => 'Pool Bar', 'type' => 'bar', 'bill_prefix' => 'PB']);
    $waiter = tenantUserAs(tenant('sunrise'), DefaultRole::Waiter);
    $gm = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/restaurant/access'))->assertOk()->assertSeeHtml('data-access-grid');
    put(tenantUrl('sunrise', '/restaurant/access'), ['users' => [$waiter->id, $gm->id], 'access' => [$bar->id => [$waiter->id]]])->assertSessionHas('success');

    $access = app(OutletAccess::class);
    expect(booking(fn () => $access->outletIds($waiter->id)))->toBe([$bar->id])
        ->and(booking(fn () => $access->canUse($waiter->id, $main->id)))->toBeFalse()
        ->and(booking(fn () => $access->outletIds($gm->id)))->toBeNull();

    put(tenantUrl('sunrise', '/restaurant/access'), ['users' => [$waiter->id], 'access' => []])->assertSessionHas('success');
    expect(booking(fn (): int => DB::table('outlet_user')->count()))->toBe(0);
});

it('lets the outlet cashier look but not change, and keeps others out', function (): void {
    staffUser(DefaultRole::FnbManager);
    $outlet = newOutlet();

    staffUser(DefaultRole::OutletCashier);
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}"))->assertOk()->assertDontSeeHtml('data-add-station')->assertDontSeeHtml('data-save-floor');
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/stations"), ['name' => 'Grill', 'output' => 'display'])->assertForbidden();
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet->id}/areas"), ['name' => 'Indoor'])->assertForbidden();
    get(tenantUrl('sunrise', '/restaurant/access'))->assertForbidden();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/restaurant/outlets'))->assertForbidden();
});

it('does not show another tenant\'s outlet or let its tables be moved', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = booking(fn (): DiningTable => DiningTable::factory()->create(), 'greenvalley');
    staffUser(DefaultRole::FnbManager);
    $mine = newOutlet();
    $area = booking(fn (): DiningArea => DiningArea::factory()->create(['outlet_id' => $mine->id]));

    get(tenantUrl('sunrise', "/restaurant/outlets/{$theirs->outlet_id}"))->assertNotFound();
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$mine->id}/areas/{$area->id}/positions"), ['positions' => [['id' => $theirs->id, 'x' => 0, 'y' => 0]]])
        ->assertUnprocessable();
});
