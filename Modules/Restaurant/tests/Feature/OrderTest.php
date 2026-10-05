<?php

/*
| Orders & kitchen tickets (Step 3.4). "Done when": a waiter opens a table, orders 5 items with
| modifiers, sends them, and two KOTs print for two stations; a voided item needs the manager PIN.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosTerminal;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\get;
use function Pest\Laravel\json;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

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

it('takes a table\'s order with modifiers and sends one ticket per station (done when)', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    ['items' => $items, 'modifiers' => $mods] = $setup;

    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertSeeHtml('data-pos-table="T1" data-status="available"');
    $order = openTable($setup['tables']['T1'], 2);
    expect($order->order_no)->toBe('MR-0001')
        ->and($order->covers)->toBe(2)
        ->and(booking(fn () => DiningTable::query()->findOrFail($setup['tables']['T1'])->status))->toBe(TableStatus::Occupied);

    get(tenantUrl('sunrise', '/pos/orders/'.$order->id))->assertOk()->assertSee('Chicken curry')->assertSeeHtml('data-ticket');

    orderApi('POST', $order, '/lines', ['item_id' => $items['curry'], 'variant_id' => $setup['variants']['Full'], 'modifier_ids' => [$mods['hot']], 'seat' => 1, 'notes' => 'No coriander'])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $items['naan'], 'quantity' => 2, 'modifier_ids' => [$mods['butter'], $mods['garlic']], 'unit_price' => '1.00'])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $items['cake']])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $items['mojito'], 'quantity' => 2])->assertOk();
    $response = orderApi('POST', $order, '/lines', ['item_id' => $items['lemonade']])->assertOk();

    // The server prices everything: 650 + (100 + 30 + 20) × 2 + 450 + 350 × 2 + 200.
    expect($response->json('order.subtotal'))->toBe('2300.00')
        ->and($response->json('order.pending'))->toBe(5)
        ->and($response->json('order.lines.1.modifiers'))->toBe([['group' => 'Add-ons', 'name' => 'Extra butter', 'price' => '30.00'], ['group' => 'Add-ons', 'name' => 'Garlic', 'price' => '20.00']])
        ->and($response->json('order.lines.1.line_total'))->toBe('300.00');

    $sent = orderApi('POST', $order, '/send')->assertOk()->assertJsonPath('message', '2 kitchen tickets sent.');
    $kots = booking(fn () => Kot::query()->with('lines')->orderBy('kot_no')->get());

    expect($kots)->toHaveCount(2)
        ->and($kots->pluck('kot_no')->all())->toBe([1, 2])
        ->and($kots->pluck('kitchen_station_id')->all())->toBe([$setup['stations']['Hot kitchen'], $setup['stations']['Bar']])
        ->and($kots[0]->lines)->toHaveCount(3)
        ->and($kots[1]->lines->pluck('quantity')->all())->toBe([2, 1])
        ->and($sent->collect('order.kots')->where('print', true)->count())->toBe(2)
        ->and($sent->collect('order.lines')->pluck('status')->unique()->all())->toBe(['sent']);

    get(tenantUrl('sunrise', '/pos/kots/'.$kots[0]->id.'/print'))->assertOk()
        ->assertSeeHtml('data-kot-ticket="1"')->assertSee('Hot kitchen')->assertSee('Table T1')->assertSee('1 × Chicken curry (Full)')->assertSee('+ Hot')->assertSee('** No coriander')->assertSee('Seat 1');
    expect(booking(fn () => Kot::query()->findOrFail($kots[0]->id)->printed_at))->not->toBeNull();

    orderApi('POST', $order, '/send')->assertStatus(422)->assertJsonPath('message', 'Nothing new to send.');
});

it('only prints for stations with a printer, and sends lines without a station on their own ticket', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    booking(fn () => OutletMenuItem::query()->where('menu_item_id', $setup['items']['steak'])->update(['kitchen_station_id' => $setup['stations']['Grill']]));

    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['steak']])->assertOk();
    $sent = orderApi('POST', $order, '/send')->assertOk();

    expect($sent->collect('order.kots')->where('print', true)->count())->toBe(0);
});

it('refuses what the outlet cannot sell: other outlets\' items, sold-out items, missing choices and unpriced open items', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    $items = $setup['items'];

    orderApi('POST', $order, '/lines', ['item_id' => $items['beer']])->assertStatus(422)->assertJsonPath('message', 'This outlet does not sell that item.');
    orderApi('POST', $order, '/lines', ['item_id' => $items['lobster']])->assertStatus(422)->assertJsonPath('message', 'Lobster is sold out.');
    orderApi('POST', $order, '/lines', ['item_id' => $items['curry'], 'variant_id' => $setup['variants']['Half']])->assertStatus(422)->assertJsonPath('message', 'Choose a spice level.');
    orderApi('POST', $order, '/lines', ['item_id' => $items['curry']])->assertStatus(422)->assertJsonPath('message', 'This outlet does not sell that item.');
    orderApi('POST', $order, '/lines', ['item_id' => $items['naan'], 'modifier_ids' => [$setup['modifiers']['hot']]])->assertStatus(422)->assertJsonPath('message', 'Some choices do not belong to this item.');
    orderApi('POST', $order, '/lines', ['item_id' => $items['special'], 'open_price' => '500'])->assertStatus(422)->assertJsonPath('message', 'Only a manager or cashier can price an open item.');
    orderApi('POST', $order, '/lines', ['item_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('item_id');
    orderApi('POST', $order, '/lines', ['item_id' => $items['naan'], 'quantity' => 0])->assertStatus(422)->assertJsonValidationErrors('quantity');

    expect(booking(fn () => PosOrderLine::query()->count()))->toBe(0);
});

it('lets a cashier price an open item', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::OutletCashier, '2222');
    $order = openTable($setup['tables']['T1']);

    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['special']])->assertStatus(422)->assertJsonPath('message', 'Enter the price of the open item.');
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['special'], 'open_price' => '725.5', 'quantity' => 2])->assertOk()
        ->assertJsonPath('order.lines.0.unit_price', '725.50')->assertJsonPath('order.subtotal', '1451.00');
});

it('changes and removes lines until they are sent', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'modifier_ids' => [$setup['modifiers']['butter']]])->json('order.lines.0.id');
    $other = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->json('order.lines.1.id');

    orderApi('PATCH', $order, '/lines/'.$line, ['quantity' => 3, 'seat' => 2, 'course' => 'starter', 'notes' => 'Crispy'])->assertOk()
        ->assertJsonPath('order.lines.0.line_total', '390.00')->assertJsonPath('order.subtotal', '740.00');
    $changed = storedLine($line);
    expect([$changed->quantity, $changed->seat_no, $changed->notes, $changed->course->value])->toBe([3, 2, 'Crispy', 'starter']);

    orderApi('DELETE', $order, '/lines/'.$other)->assertOk()->assertJsonPath('order.subtotal', '390.00');
    orderApi('POST', $order, '/send')->assertOk();

    orderApi('PATCH', $order, '/lines/'.$line, ['quantity' => 1])->assertStatus(422)->assertJsonPath('message', 'This line went to the kitchen: void it instead.');
    orderApi('DELETE', $order, '/lines/'.$line)->assertStatus(422);
    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'changed_mind'])->assertStatus(422);
    expect(storedLine($line)->quantity)->toBe(3);
});

it('holds a course and fires it later on its own ticket', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    $held = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['cake'], 'quantity' => 2, 'held' => true])->assertOk();

    expect($held->json('order.pending'))->toBe(1)->and($held->json('order.held'))->toBe(['dessert']);

    orderApi('POST', $order, '/send')->assertOk();
    expect(booking(fn () => Kot::query()->count()))->toBe(1);

    orderApi('POST', $order, '/send', ['course' => 'starter'])->assertStatus(422)->assertJsonPath('message', 'Nothing is held for starter.');
    $fired = orderApi('POST', $order, '/send', ['course' => 'dessert'])->assertOk();

    $kot = booking(fn () => Kot::query()->with('lines')->orderByDesc('kot_no')->first());
    expect($kot?->kot_no)->toBe(2)
        ->and($kot?->lines->pluck('quantity')->all())->toBe([2])
        ->and($fired->json('order.held'))->toBe([])
        ->and($fired->collect('order.lines')->pluck('status')->all())->toBe(['sent', 'sent']);
});

it('voids a sent line only with a manager\'s PIN, telling the kitchen with a void ticket', function (): void {
    $setup = orderSetup();
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito'], 'quantity' => 2])->json('order.lines.0.id');
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['lemonade']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    get(tenantUrl('sunrise', '/pos/orders/'.$order->id))->assertSeeHtml('data-approver="'.$manager->id.'"');
    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'changed_mind'])->assertStatus(422)->assertJsonPath('message', 'Voiding needs a manager\'s approval.');
    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'other'])->assertStatus(422)->assertJsonValidationErrors('note');

    $approve = fn (string $pin): TestResponse => json('POST', tenantUrl('sunrise', '/pos/api/approvals'), ['action' => 'order.void-line', 'manager_id' => $manager->id, 'pin' => $pin, 'subject_id' => $line]);
    $approve('1234')->assertStatus(422);
    $approval = $approve('9999')->assertOk()->json('approval_id');

    $voided = orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'changed_mind', 'wastage' => true, 'approval_id' => $approval])->assertOk()->assertJsonPath('message', 'Voided; the kitchen is told.');
    expect($voided->json('order.subtotal'))->toBe('200.00')
        ->and($voided->json('order.lines.0.status'))->toBe('voided')
        ->and($voided->collect('order.kots')->last())->toMatchArray(['type' => 'void', 'no' => 2, 'print' => true])
        ->and(storedLine($line)->only(['status', 'is_wastage', 'manager_approval_id']))->toBe(['status' => OrderLineStatus::Voided, 'is_wastage' => true, 'manager_approval_id' => $approval]);

    $voidKot = booking(fn () => Kot::query()->where('type', KotType::Void->value)->sole());
    get(tenantUrl('sunrise', '/pos/kots/'.$voidKot->id.'/print'))->assertOk()->assertSee('VOID')->assertSee('2 × Virgin mojito')->assertSee('Reason: Guest changed mind');

    // The approval is used once.
    $lemonade = booking(fn () => PosOrderLine::query()->where('menu_item_id', $setup['items']['lemonade'])->sole());
    orderApi('POST', $order, '/lines/'.$lemonade->id.'/void', ['reason' => 'changed_mind', 'approval_id' => $approval])->assertStatus(422);
    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'changed_mind'])->assertStatus(422)->assertJsonPath('message', 'This line cannot be voided.');
});

it('lets a manager void without a PIN', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::FnbManager, '9999');
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->json('order.lines.0.id');
    orderApi('POST', $order, '/send')->assertOk();

    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'quality_issue', 'note' => 'Burnt'])->assertOk()->assertJsonPath('order.subtotal', '0.00');
    expect(storedLine($line)->only(['void_note', 'manager_approval_id', 'is_wastage']))->toBe(['void_note' => 'Burnt', 'manager_approval_id' => null, 'is_wastage' => false]);
});

it('moves an order to a free table, merges another order in, and cancels an order nothing was sent for', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    ['T1' => $t1, 'T2' => $t2, 'T3' => $t3] = $setup['tables'];
    $first = openTable($t1, 2);
    orderApi('POST', $first, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $first, '/send')->assertOk();
    $second = openTable($t2, 3);
    orderApi('POST', $second, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();
    orderApi('POST', $second, '/send')->assertOk();

    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'dine_in', 'table_id' => $t1])->assertSessionHas('error', 'Table T1 already has an open order.');
    orderApi('POST', $first, '/transfer', ['table_id' => $t2])->assertStatus(422)->assertJsonPath('message', 'Table T2 has an open order: merge them instead.');
    orderApi('POST', $first, '/transfer', ['table_id' => $t3])->assertOk()->assertJsonPath('order.table', 'T3');
    $status = fn (int $id): TableStatus => booking(fn () => DiningTable::query()->findOrFail($id)->status);
    expect($status($t1))->toBe(TableStatus::Available)->and($status($t3))->toBe(TableStatus::Occupied);

    orderApi('POST', $first, '/merge', ['order_id' => $second->id])->assertOk()->assertJsonPath('order.subtotal', '450.00')->assertJsonPath('order.covers', 5);
    expect(storedOrder($second->id)->only(['status', 'merged_into_id']))->toBe(['status' => OrderStatus::Cancelled, 'merged_into_id' => $first->id])
        ->and($status($t2))->toBe(TableStatus::Available)
        ->and(booking(fn () => Kot::query()->where('pos_order_id', $first->id)->count()))->toBe(2);
    orderApi('POST', $second, '/lines', ['item_id' => $setup['items']['naan']])->assertStatus(422)->assertJsonPath('message', 'This order is closed.');

    orderApi('POST', $first, '/cancel')->assertStatus(422)->assertJsonPath('message', 'Items went to the kitchen: void them, then settle the order.');
    $third = openTable($t2, 1);
    orderApi('POST', $third, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $third, '/cancel')->assertOk()->assertJsonPath('redirect', tenantUrl('sunrise', '/pos/floor'));
    expect(storedOrder($third->id)->status)->toBe(OrderStatus::Cancelled)->and($status($t2))->toBe(TableStatus::Available)
        ->and(booking(fn () => PosOrderLine::query()->where('pos_order_id', $third->id)->count()))->toBe(0);
    get(tenantUrl('sunrise', '/pos/orders/'.$third->id))->assertRedirect(tenantUrl('sunrise', '/pos/floor'));
});

it('numbers orders and kitchen tickets per outlet and business date, and takes takeaway orders', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $first = openTable($setup['tables']['T1']);
    post(tenantUrl('sunrise', '/pos/orders'), ['type' => 'takeaway'])->assertRedirect();
    $takeaway = booking(fn (): PosOrder => PosOrder::query()->whereNull('dining_table_id')->sole());

    expect($takeaway->order_no)->toBe('MR-0002')->and($takeaway->order_type->value)->toBe('takeaway');

    foreach ([$first, $takeaway, $first] as $order) {
        orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
        orderApi('POST', $order, '/send')->assertOk();
    }

    expect(booking(fn () => Kot::query()->orderBy('id')->pluck('kot_no')->all()))->toBe([1, 2, 3]);
    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertSeeHtml('data-open-order="MR-0001"')->assertSeeHtml('data-open-order="MR-0002"')
        ->assertSeeHtml('data-pos-table="T1" data-status="occupied"');
});

it('keeps orders to staff who may take them, on a terminal of the order\'s outlet', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);

    // A terminal of another outlet does not see the order.
    [$elsewhere] = booking(fn (): array => RegisterTerminal::make()->handle(Outlet::query()->where('code', 'PB')->sole(), 'Pool bar till'));
    $bartender = posStaff(DefaultRole::Bartender, '3333', $elsewhere);
    onDevice($elsewhere);
    pinSignIn($bartender, '3333')->assertRedirect();
    orderApi('GET', $order)->assertNotFound();
    get(tenantUrl('sunrise', '/pos/orders/'.$order->id))->assertNotFound();

    // Staff without restaurant.order.take (an accountant cannot even sign in on the POS).
    booking(fn () => Role::findByName(DefaultRole::Bartender->label())->revokePermissionTo('restaurant.order.take'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    get(tenantUrl('sunrise', '/pos/floor'))->assertForbidden();
});

it('refuses an order screen without a signed-in person', function (): void {
    $setup = orderSetup();
    $order = booking(fn (): PosOrder => PosOrder::factory()->create(['outlet_id' => $setup['terminal']->outlet_id]));
    onDevice($setup['terminal']);

    get(tenantUrl('sunrise', '/pos/floor'))->assertRedirect(tenantUrl('sunrise', '/pos'));
    orderApi('POST', $order, '/send')->assertStatus(401)->assertJsonPath('message', 'The terminal is locked; sign in with your PIN.');
    expect(booking(fn () => Kot::query()->count()))->toBe(0);
});
