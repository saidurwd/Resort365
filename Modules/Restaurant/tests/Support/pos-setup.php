<?php

/*
| POS test helpers (Steps 3.3–3.5): a terminal, staff with PINs, the device cookie and PIN sign-in; the
| order setup (stations, tables, a small menu) and the order API.
*/

use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\Models\FolioLine;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Models\User;
use Modules\Property\Models\Property;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Models\Reservation;
use Modules\Restaurant\Actions\OpenPosSession;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosDevice;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\json;
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

/**
 * The Main Restaurant's terminal, three tables, a Hot kitchen (prints), a Grill (display only) and a
 * Bar (prints), and a small menu: curry (Half/Full, spice level required), naan (add-ons), lava cake,
 * mojito, lemonade, an open item, a sold-out item, and an item another outlet sells.
 *
 * @return array{terminal: PosTerminal, tables: array<string, int>, items: array<string, int>, variants: array<string, int>, modifiers: array<string, int>, stations: array<string, int>}
 */
function orderSetup(): array
{
    [$terminal] = posTerminal();

    return booking(function () use ($terminal): array {
        $outlet = $terminal->outlet;
        $other = Outlet::factory()->create(['property_id' => $outlet->property_id, 'code' => 'PB', 'name' => 'Pool Bar']);
        $area = DiningArea::factory()->create(['outlet_id' => $outlet->id, 'name' => 'Indoor']);
        $tables = [];

        foreach (['T1', 'T2', 'T3'] as $number) {
            $tables[$number] = DiningTable::factory()->create(['outlet_id' => $outlet->id, 'dining_area_id' => $area->id, 'number' => $number])->id;
        }

        $stations = [];

        foreach (['Hot kitchen' => StationOutput::Both, 'Grill' => StationOutput::Display, 'Bar' => StationOutput::Printer] as $name => $output) {
            $stations[$name] = KitchenStation::factory()->create(['outlet_id' => $outlet->id, 'name' => $name, 'output' => $output, 'sort_order' => count($stations)])->id;
        }

        $spice = ModifierGroup::factory()->create(['name' => 'Spice level', 'min_select' => 1, 'max_select' => 1]);
        $addOns = ModifierGroup::factory()->create(['name' => 'Add-ons', 'min_select' => 0, 'max_select' => 2]);
        $modifiers = [
            'mild' => Modifier::factory()->create(['modifier_group_id' => $spice->id, 'name' => 'Mild'])->id,
            'hot' => Modifier::factory()->create(['modifier_group_id' => $spice->id, 'name' => 'Hot'])->id,
            'butter' => Modifier::factory()->create(['modifier_group_id' => $addOns->id, 'name' => 'Extra butter', 'price_delta' => '30.00'])->id,
            'garlic' => Modifier::factory()->create(['modifier_group_id' => $addOns->id, 'name' => 'Garlic', 'price_delta' => '20.00'])->id,
        ];

        $items = [];
        $variants = [];
        $sell = function (string $key, string $name, string $course, string $price, ?string $station, array $extra = [], ?Outlet $at = null) use (&$items, $outlet, $stations): MenuItem {
            $item = MenuItem::factory()->create(['code' => strtoupper($key), 'name' => ['en' => $name], 'course' => $course, ...$extra]);
            $items[$key] = $item->id;
            OutletMenuItem::factory()->create(['outlet_id' => ($at ?? $outlet)->id, 'menu_item_id' => $item->id, 'price' => $price, 'kitchen_station_id' => $station !== null ? $stations[$station] : null]);

            return $item;
        };

        $curry = MenuItem::factory()->create(['code' => 'CURRY', 'name' => ['en' => 'Chicken curry'], 'course' => 'main']);
        $items['curry'] = $curry->id;
        $curry->modifierGroups()->attach($spice->id, ['tenant_id' => $curry->tenant_id, 'sort_order' => 0]);

        foreach (['Half' => '380.00', 'Full' => '650.00'] as $size => $price) {
            $variant = MenuItemVariant::factory()->create(['menu_item_id' => $curry->id, 'name' => $size]);
            $variants[$size] = $variant->id;
            OutletMenuItem::factory()->create(['outlet_id' => $outlet->id, 'menu_item_id' => $curry->id, 'menu_item_variant_id' => $variant->id, 'variant_key' => $variant->id,
                'price' => $price, 'kitchen_station_id' => $stations['Hot kitchen']]);
        }

        $naan = $sell('naan', 'Naan', 'side', '100.00', 'Hot kitchen');
        $naan->modifierGroups()->attach($addOns->id, ['tenant_id' => $naan->tenant_id, 'sort_order' => 0]);
        $sell('cake', 'Lava cake', 'dessert', '450.00', 'Hot kitchen');
        $sell('mojito', 'Virgin mojito', 'drink', '350.00', 'Bar');
        $sell('lemonade', 'Lemonade', 'drink', '200.00', 'Bar');
        $sell('steak', 'Steak', 'main', '1800.00', null);
        $sell('special', 'Chef\'s special', 'main', '0.00', 'Hot kitchen', ['kind' => MenuItemKind::Open]);
        $sell('lobster', 'Lobster', 'main', '3500.00', 'Hot kitchen');
        OutletMenuItem::query()->where('menu_item_id', $items['lobster'])->update(['is_available' => false]);
        $sell('beer', 'Pool bar only', 'drink', '300.00', null, [], $other);

        return ['terminal' => $terminal, 'tables' => $tables, 'items' => $items, 'variants' => $variants, 'modifiers' => $modifiers, 'stations' => $stations];
    });
}

/**
 * A member of staff signed in by PIN on the terminal.
 */
function onTerminal(PosTerminal $terminal, DefaultRole $role, string $pin): void
{
    $user = posStaff($role, $pin, $terminal);
    onDevice($terminal);
    pinSignIn($user, $pin)->assertRedirect(tenantUrl('sunrise', '/pos/main'));
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function orderApi(string $method, PosOrder|int $order, string $path = '', array $data = []): TestResponse
{
    $id = $order instanceof PosOrder ? $order->id : $order;

    return json($method, tenantUrl('sunrise', '/pos/api/orders/'.$id.$path), $data);
}

function openTable(int $tableId, int $covers = 2): PosOrder
{
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'dine_in', 'table_id' => $tableId, 'covers' => $covers])->assertRedirect();

    return booking(fn (): PosOrder => PosOrder::query()->where('dining_table_id', $tableId)->where('status', 'open')->sole());
}

function storedLine(int $id): PosOrderLine
{
    return booking(fn (): PosOrderLine => PosOrderLine::query()->findOrFail($id));
}

function storedOrder(int $id): PosOrder
{
    return booking(fn (): PosOrder => PosOrder::query()->findOrFail($id));
}

/**
 * The order setup, with the outlet taxed SC 10% then VAT 15% (the ROOM category of the booking setup).
 *
 * @return array<string, mixed>
 */
function billSetup(bool $inclusive = false): array
{
    $setup = orderSetup();
    booking(fn () => DB::table('outlets')->where('id', $setup['terminal']->outlet_id)->update([
        'default_tax_category_id' => DB::table('tax_categories')->where('tenant_id', tenant('sunrise')->id)->where('code', 'ROOM')->value('id'), 'prices_include_tax' => $inclusive,
    ]));

    return $setup;
}

/**
 * A sent order at T1: 2 × curry (Full), 4 × naan, 4 × mojito = 3,100.00 before tax.
 *
 * @param  array<string, mixed>  $setup
 */
function fourCovers(array $setup): PosOrder
{
    $order = openTable($setup['tables']['T1'], 4);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['curry'], 'variant_id' => $setup['variants']['Full'], 'modifier_ids' => [$setup['modifiers']['mild']], 'quantity' => 2])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 4, 'seat' => 1])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito'], 'quantity' => 4, 'seat' => 2])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    return $order;
}

function openSession(PosTerminal $terminal, int $userId): PosSession
{
    return booking(fn (): PosSession => OpenPosSession::make()->handle($terminal, $userId, '2000'));
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function billApi(string $path, array $data = []): TestResponse
{
    return json('POST', tenantUrl('sunrise', '/pos/api/'.$path), $data);
}

function storedBill(string $billNo): PosBill
{
    return booking(fn (): PosBill => PosBill::query()->where('bill_no', $billNo)->sole());
}

function roomDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * A guest checked in to a room from $before nights ago for $before + $after nights.
 */
function guestIn(string $room = '401', int $before = 2, int $after = 0): Reservation
{
    $reservation = bookStay([$room], roomDay()->subDays($before)->toDateString(), roomDay()->addDays($after)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);
    booking(fn () => app(StayOperations::class)->checkIn($reservation->id));

    return freshReservation($reservation->id);
}

/**
 * The cashier signed in on the terminal with the session open.
 *
 * @param  array<string, mixed>  $setup
 */
function cashierOn(array $setup): PosSession
{
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();

    return openSession($setup['terminal'], $cashier->id);
}

/**
 * A printed bill for 2 naan and a mojito: 550.00 + SC 55.00 + VAT 90.75 = 695.75.
 *
 * @param  array<string, mixed>  $setup
 * @return array<string, mixed>
 */
function naanAndMojito(array $setup, string $table = 'T1'): array
{
    $order = openTable($setup['tables'][$table]);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    return billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
}

/**
 * @param  array<string, mixed>  $data
 * @return TestResponse<Response>
 */
function chargeRoom(int $billId, string $amount, int $reservationId, array $data = []): TestResponse
{
    return billApi("bills/{$billId}/payments", ['method' => 'room_charge', 'amount' => $amount, 'reservation_id' => $reservationId, ...$data]);
}

/**
 * The guest's name as the POS shows it (with the salutation the factory gave).
 */
function stayGuest(Reservation $stay): string
{
    return booking(fn (): string => app(FolioPostingContract::class)->chargeableStays(bookingIds()['property'], $stay->code)[0]->guestName);
}

function restaurantLine(int $billId): FolioLine
{
    return booking(fn (): FolioLine => FolioLine::query()->where('reference_type', 'pos_bill')->where('reference_id', $billId)->sole());
}
