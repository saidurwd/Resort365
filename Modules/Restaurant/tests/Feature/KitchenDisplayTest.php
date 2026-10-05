<?php

/*
| Real-time & kitchen display (Step 3.5). "Done when": an item sent from a tablet appears on the KDS
| within 2 seconds (here: the KotSent broadcast on the station's channel and the board that shows it;
| the 2 seconds are checked in the browser with Reverb running); marking it ready notifies the POS;
| channels never leak between tenants.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Modules\Restaurant\Actions\MarkSoldOut;
use Modules\Restaurant\Actions\RegisterStationDisplay;
use Modules\Restaurant\Broadcasting\RestaurantChannels;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Events\KotItemStatusChanged;
use Modules\Restaurant\Events\KotItemVoided;
use Modules\Restaurant\Events\KotSent;
use Modules\Restaurant\Events\MenuAvailabilityChanged;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Services\KdsDevice;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\json;
use function Pest\Laravel\post;
use function Pest\Laravel\withCookie;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
    Event::fake([KotSent::class, KotItemVoided::class, KotItemStatusChanged::class, TableStatusChanged::class, MenuAvailabilityChanged::class]);
});

/**
 * This browser becomes the station's kitchen display.
 */
function onKitchenScreen(int $stationId): string
{
    $token = booking(fn (): string => RegisterStationDisplay::make()->handle(KitchenStation::query()->findOrFail($stationId)));
    $station = booking(fn (): KitchenStation => KitchenStation::query()->findOrFail($stationId));
    withCookie(KdsDevice::COOKIE, $station->id.'|'.$station->display_token);

    return $token;
}

/**
 * @return TestResponse<Response>
 */
function kdsTap(int $kotId, string $action): TestResponse
{
    return json('POST', tenantUrl('sunrise', '/kds/api/kots/'.$kotId), ['action' => $action]);
}

/**
 * @return TestResponse<Response>
 */
function subscribe(string $channel): TestResponse
{
    return post(tenantUrl('sunrise', '/broadcasting/auth'), ['socket_id' => '1234.5678', 'channel_name' => 'private-'.$channel]);
}

/**
 * @return list<string>
 */
function channelsOf(object $event): array
{
    return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
}

it('shows a sent ticket on its station\'s display and tells the POS when it is ready (done when)', function (): void {
    $setup = orderSetup();
    ['Hot kitchen' => $hot, 'Bar' => $bar] = $setup['stations'];
    $tenantId = tenant('sunrise')->id;
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['curry'], 'variant_id' => $setup['variants']['Full'], 'modifier_ids' => [$setup['modifiers']['hot']], 'notes' => 'No coriander'])->assertOk();
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    $sent = [];
    Event::assertDispatched(KotSent::class, function (KotSent $event) use (&$sent): bool {
        $sent[] = channelsOf($event);

        return true;
    });
    expect($sent)->toBe([['private-tenant.'.$tenantId.'.kitchen.'.$hot], ['private-tenant.'.$tenantId.'.kitchen.'.$bar]]);
    Event::assertDispatched(TableStatusChanged::class, fn (TableStatusChanged $event): bool => channelsOf($event) === ['private-tenant.'.$tenantId.'.outlet.'.$setup['terminal']->outlet_id]);

    onKitchenScreen($hot);
    get(tenantUrl('sunrise', '/kds'))->assertOk()->assertSeeHtml('data-kds-board="'.$hot.'"')->assertSee('Hot kitchen');
    $board = json('GET', tenantUrl('sunrise', '/kds/api/board'))->assertOk();
    expect($board->json('board.tickets'))->toHaveCount(1)
        ->and($board->json('board.tickets.0'))->toMatchArray(['no' => 1, 'status' => 'new', 'where' => 'Table T1', 'order_no' => 'MR-0001'])
        ->and($board->json('board.tickets.0.lines.0'))->toMatchArray(['quantity' => 1, 'name' => 'Chicken curry', 'variant' => 'Full', 'modifiers' => ['Hot'], 'notes' => 'No coriander'])
        ->and($board->json('board.warn'))->toBe(10)->and($board->json('board.late'))->toBe(20);

    $kot = booking(fn (): Kot => Kot::query()->where('kitchen_station_id', $hot)->sole());
    kdsTap($kot->id, 'start')->assertOk()->assertJsonPath('board.tickets.0.status', 'preparing');
    kdsTap($kot->id, 'ready')->assertOk()->assertJsonPath('board.tickets.0.status', 'ready');

    Event::assertDispatched(KotItemStatusChanged::class, fn (KotItemStatusChanged $event): bool => $event->status === 'ready'
        && channelsOf($event) === ['private-tenant.'.$tenantId.'.outlet.'.$setup['terminal']->outlet_id, 'private-tenant.'.$tenantId.'.kitchen.'.$hot]
        && $event->broadcastWith()['items'] === ['1 × Chicken curry (Full)'] && $event->broadcastWith()['table'] === 'T1');
    expect(booking(fn () => DB::table('pos_order_lines')->where('pos_order_id', $order->id)->orderBy('id')->pluck('status')->all()))->toBe(['ready', 'sent']);

    // Without WebSockets the POS asks for dishes made ready since it last looked.
    $ready = json('GET', tenantUrl('sunrise', '/pos/api/ready?since='.urlencode(now()->subMinute()->toIso8601String())))->assertOk();
    expect($ready->json('ready'))->toHaveCount(1)->and($ready->json('ready.0'))->toMatchArray(['order_id' => $order->id, 'table' => 'T1', 'items' => ['1 × Chicken curry (Full)']]);
    expect(json('GET', tenantUrl('sunrise', '/pos/api/ready?since='.urlencode(now()->addMinute()->toIso8601String())))->json('ready'))->toBe([]);
    get(tenantUrl('sunrise', '/pos/floor'))->assertOk()->assertSeeHtml('posReady(');

    // Bump: served and off the board; recall puts it back.
    kdsTap($kot->id, 'bump')->assertOk()->assertJsonPath('board.tickets', [])->assertJsonPath('board.recall.no', 1);
    expect(storedLine(booking(fn () => (int) DB::table('pos_order_lines')->where('pos_order_id', $order->id)->min('id')))->status)->toBe(OrderLineStatus::Served)
        ->and(booking(fn () => Kot::query()->findOrFail($kot->id))->only(['status']))->toBe(['status' => KotStatus::Done]);
    kdsTap($kot->id, 'recall')->assertOk()->assertJsonPath('board.tickets.0.status', 'ready');
    kdsTap($kot->id, 'start')->assertStatus(422)->assertJsonPath('message', 'Ticket 1 is ready.');
    kdsTap($kot->id, 'explode')->assertStatus(422)->assertJsonValidationErrors('action');
});

it('shows a voided item struck through and a void ticket the kitchen acknowledges', function (): void {
    $setup = orderSetup();
    $hot = $setup['stations']['Hot kitchen'];
    onTerminal($setup['terminal'], DefaultRole::FnbManager, '9999');
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->json('order.lines.0.id');
    orderApi('POST', $order, '/send')->assertOk();
    orderApi('POST', $order, '/lines/'.$line.'/void', ['reason' => 'wrong_order'])->assertOk();

    Event::assertDispatched(KotItemVoided::class, fn (KotItemVoided $event): bool => $event->orderLineId === $line
        && channelsOf($event) === ['private-tenant.'.tenant('sunrise')->id.'.kitchen.'.$hot]);

    onKitchenScreen($hot);
    $board = json('GET', tenantUrl('sunrise', '/kds/api/board'))->assertOk();
    expect($board->json('board.tickets.0.lines.0.voided'))->toBeTrue()
        ->and($board->json('board.tickets.1'))->toMatchArray(['type' => 'void', 'no' => 2])
        ->and($board->json('board.tickets.1.lines.0.void_reason'))->toBe('Wrong order');

    $void = booking(fn (): Kot => Kot::query()->where('type', 'void')->sole());
    kdsTap($void->id, 'start')->assertStatus(422);
    kdsTap($void->id, 'bump')->assertOk()->assertJsonCount(1, 'board.tickets');
});

it('highlights allergens of the items on a ticket', function (): void {
    $setup = orderSetup();
    booking(fn () => DB::table('menu_items')->where('id', $setup['items']['naan'])->update(['allergens' => json_encode(['gluten', 'milk'])]));
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();

    onKitchenScreen($setup['stations']['Hot kitchen']);
    expect(json('GET', tenantUrl('sunrise', '/kds/api/board'))->json('board.tickets.0.lines.0.allergens'))->toBe(['Gluten', 'Milk']);
});

it('registers a kitchen screen with its station\'s display token, and signs it out when the token changes', function (): void {
    $setup = orderSetup();
    ['Hot kitchen' => $hot, 'Bar' => $bar] = $setup['stations'];
    staffUser(DefaultRole::FnbManager);
    $outlet = $setup['terminal']->outlet_id;

    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet}/stations/{$bar}/display-token"))->assertSessionHas('error', 'Bar only prints its tickets: choose a display output first.');
    post(tenantUrl('sunrise', "/restaurant/outlets/{$outlet}/stations/{$hot}/display-token"))->assertSessionHas('display_token');
    $token = session('display_token')['token'];
    get(tenantUrl('sunrise', "/restaurant/outlets/{$outlet}"))->assertOk()->assertSeeHtml('data-display-token="Hot kitchen"');

    auth()->guard('web')->logout();
    get(tenantUrl('sunrise', '/kds'))->assertRedirect(tenantUrl('sunrise', '/kds/register'));
    post(tenantUrl('sunrise', '/kds/register'), ['token' => 'NOPE'])->assertSessionHasErrors('token');
    $stored = booking(fn (): KitchenStation => KitchenStation::query()->findOrFail($hot));
    post(tenantUrl('sunrise', '/kds/register'), ['token' => strtolower($token)])->assertRedirect(tenantUrl('sunrise', '/kds'))
        ->assertCookie(KdsDevice::COOKIE, $hot.'|'.$stored->display_token);

    withCookie(KdsDevice::COOKIE, $hot.'|'.$stored->display_token);
    get(tenantUrl('sunrise', '/kds'))->assertOk()->assertSeeHtml('data-kds-sign-out');

    booking(fn () => RegisterStationDisplay::make()->handle($stored));
    get(tenantUrl('sunrise', '/kds'))->assertRedirect(tenantUrl('sunrise', '/kds/register'));
    json('GET', tenantUrl('sunrise', '/kds/api/board'))->assertStatus(401);
});

it('lets chefs and managers open a station of their outlets, and nobody else', function (): void {
    $setup = orderSetup();
    ['Hot kitchen' => $hot, 'Bar' => $bar, 'Grill' => $grill] = $setup['stations'];
    $chef = posStaff(DefaultRole::Chef, '4444', $setup['terminal']);
    actingAs($chef);

    get(tenantUrl('sunrise', '/kds/stations'))->assertOk()->assertSeeHtml('data-station="Hot kitchen"')->assertSeeHtml('data-station="Grill"')->assertDontSeeHtml('data-station="Bar"');
    get(tenantUrl('sunrise', '/kds?station='.$grill))->assertOk()->assertSeeHtml('data-kds-board="'.$grill.'"')->assertSee('Other station');
    get(tenantUrl('sunrise', '/kds'))->assertOk()->assertSeeHtml('data-kds-board="'.$grill.'"'); // remembered
    get(tenantUrl('sunrise', '/kds?station='.$bar))->assertRedirect(tenantUrl('sunrise', '/kds/stations')); // prints only

    // Another station's ticket cannot be moved from this screen.
    $kot = booking(fn (): Kot => Kot::factory()->create(['outlet_id' => $setup['terminal']->outlet_id, 'kitchen_station_id' => $hot]));
    get(tenantUrl('sunrise', '/kds?station='.$grill))->assertOk();
    kdsTap($kot->id, 'start')->assertNotFound();

    // A chef of another outlet, and a waiter.
    DB::table('outlet_user')->where('user_id', $chef->id)->delete();
    get(tenantUrl('sunrise', '/kds?station='.$hot))->assertRedirect(tenantUrl('sunrise', '/kds/stations'));
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Waiter));
    get(tenantUrl('sunrise', '/kds/stations'))->assertForbidden();
    get(tenantUrl('sunrise', '/kds'))->assertRedirect(tenantUrl('sunrise', '/kds/register'));
});

it('keeps the POS floor and menu live, and announces 86 changes', function (): void {
    $setup = orderSetup();
    onTerminal($setup['terminal'], DefaultRole::Waiter, '1111');
    $order = openTable($setup['tables']['T2'], 3);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['mojito'], 'quantity' => 2])->assertOk();

    $floor = json('GET', tenantUrl('sunrise', '/pos/api/floor'))->assertOk();
    expect($floor->json('floor.tables.'.$setup['tables']['T2']))->toMatchArray(['status' => 'occupied', 'subtotal' => '700.00'])
        ->and($floor->json('floor.tables.'.$setup['tables']['T1'].'.status'))->toBe('available')
        ->and($floor->json('floor.orders.0'))->toMatchArray(['order_no' => 'MR-0001', 'where' => 'Table T2']);

    $row = booking(fn (): OutletMenuItem => OutletMenuItem::query()->where('menu_item_id', $setup['items']['mojito'])->sole());
    booking(fn () => MarkSoldOut::make()->handle($row, true));
    Event::assertDispatched(MenuAvailabilityChanged::class, fn (MenuAvailabilityChanged $event): bool => $event->message === 'Virgin mojito is sold out.'
        && channelsOf($event) === ['private-tenant.'.tenant('sunrise')->id.'.outlet.'.$setup['terminal']->outlet_id]);
    $menu = json('GET', tenantUrl('sunrise', '/pos/api/menu'))->assertOk();
    expect($menu->collect('menu.items')->firstWhere('name', 'Virgin mojito')['variants'][0]['sold_out'])->toBeTrue();
});

describe('private channels', function (): void {
    beforeEach(function (): void {
        // The null broadcaster grants every channel; authorize with the real (Reverb/Pusher) one.
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test',
            'options' => ['host' => 'localhost', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]]);
        app(BroadcastManager::class)->forgetDrivers();
        RestaurantChannels::register();
    });

    it('grants a kitchen screen its own station only, never another tenant\'s', function (): void {
        $setup = orderSetup();
        ['Hot kitchen' => $hot, 'Grill' => $grill] = $setup['stations'];
        $tenantId = tenant('sunrise')->id;
        $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
        onKitchenScreen($hot);

        subscribe("tenant.{$tenantId}.kitchen.{$hot}")->assertOk()->assertJsonStructure(['auth']);
        subscribe("tenant.{$tenantId}.kitchen.{$grill}")->assertForbidden();
        subscribe("tenant.{$other->id}.kitchen.{$hot}")->assertForbidden();
        subscribe("tenant.{$tenantId}.outlet.{$setup['terminal']->outlet_id}")->assertForbidden();
    });

    it('grants POS staff their outlet, and chefs their stations', function (): void {
        $setup = orderSetup();
        $tenantId = tenant('sunrise')->id;
        $outlet = $setup['terminal']->outlet_id;
        $hot = $setup['stations']['Hot kitchen'];
        $elsewhere = booking(fn (): int => Outlet::query()->where('code', 'PB')->value('id'));
        $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));

        $waiter = posStaff(DefaultRole::Waiter, '1111', $setup['terminal']);
        actingAs($waiter);
        subscribe("tenant.{$tenantId}.outlet.{$outlet}")->assertOk();
        subscribe("tenant.{$tenantId}.outlet.{$elsewhere}")->assertForbidden();
        subscribe("tenant.{$other->id}.outlet.{$outlet}")->assertForbidden();
        subscribe("tenant.{$tenantId}.kitchen.{$hot}")->assertForbidden();
        subscribe('tenant.x.outlet.'.$outlet)->assertForbidden();

        actingAs(posStaff(DefaultRole::Chef, '4444', $setup['terminal']));
        subscribe("tenant.{$tenantId}.kitchen.{$hot}")->assertOk();
        subscribe("tenant.{$tenantId}.outlet.{$outlet}")->assertForbidden();

        auth()->guard('web')->logout();
        subscribe("tenant.{$tenantId}.outlet.{$outlet}")->assertForbidden();
    });

    it('never grants a user of one tenant a channel on another tenant\'s subdomain', function (): void {
        $setup = orderSetup();
        $tenantId = tenant('sunrise')->id;
        $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
        actingAs(tenantUserAs($other, DefaultRole::GeneralManager));

        $status = subscribe("tenant.{$tenantId}.outlet.{$setup['terminal']->outlet_id}")->status();
        expect($status)->toBeIn([401, 403]);
    });
});
