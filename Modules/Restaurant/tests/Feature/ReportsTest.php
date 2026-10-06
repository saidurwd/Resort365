<?php

/*
| Restaurant reports (Step 3.8). "Done when": the F&B Manager can answer "what did the Pool Bar sell
| yesterday, who voided what, and how many breakfasts were included vs taken" from the reports.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Models\User;
use Modules\Restaurant\Models\MealEntitlementSnapshot;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosOrder;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

/**
 * The outlet is the Pool Bar and yesterday it sold: 2 naan + a mojito by card, 2 mojitos with 10% off in
 * cash, and 2 naan + a mojito whose mojito was voided (card). A CP guest took one of two breakfasts.
 *
 * @param  array<string, mixed>  $setup
 * @return array{yesterday: string, manager: User}
 */
function poolBarYesterday(array $setup): array
{
    booking(fn () => DB::table('outlets')->where('id', $setup['terminal']->outlet_id)->update(['name' => 'Pool Bar']));
    booking(fn () => DB::table('outlets')->where('code', 'PB')->where('id', '!=', $setup['terminal']->outlet_id)->update(['name' => 'Roof Bar']));
    booking(fn () => DB::table('rate_plans')->where('code', 'RO')->update(['meal_plan' => 'CP']));
    booking(fn () => OutletMenuItem::query()->where('menu_item_id', $setup['items']['naan'])->update(['is_package_eligible' => true]));
    $stay = guestIn('401', 1, 1);
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    $session = openSession($setup['terminal'], $manager->id);

    $sell = function (array $lines, string $method, ?string $discount = null, bool $void = false) use ($setup): PosOrder {
        $order = openTable($setup['tables']['T1'], 2);

        foreach ($lines as [$key, $quantity]) {
            orderApi('POST', $order, '/lines', ['item_id' => $setup['items'][$key], 'quantity' => $quantity])->assertOk();
        }

        orderApi('POST', $order, '/send')->assertOk();

        if ($void) {
            $mojito = booking(fn (): int => (int) DB::table('pos_order_lines')->where('pos_order_id', $order->id)->where('name_snapshot', 'Virgin mojito')->value('id'));
            orderApi('POST', $order, "/lines/{$mojito}/void", ['reason' => 'quality_issue', 'note' => 'Warm drink', 'wastage' => true])->assertOk();
        }

        if ($discount !== null) {
            billApi("orders/{$order->id}/discount", ['type' => 'percent', 'value' => $discount, 'reason' => 'Regular guest'])->assertOk();
        }

        $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
        billApi("bills/{$bill['id']}/payments", ['method' => $method, 'amount' => $bill['grand_total'], 'tendered' => '5000', 'reference' => '7731'])->assertOk();

        return $order;
    };
    $orders = [$sell([['naan', 2], ['mojito', 1]], 'card'), $sell([['mojito', 2]], 'cash', '10'), $sell([['naan', 2], ['mojito', 1]], 'card', null, true)];

    // One of the CP guest's two breakfasts, taken at the bar.
    $meal = openTable($setup['tables']['T1'], 1);
    orderApi('POST', $meal, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $meal, '/send')->assertOk();
    billApi("orders/{$meal->id}/meal-plan", ['reservation_id' => $stay->id, 'period' => 'breakfast', 'adults' => 1])->assertOk();
    billApi("orders/{$meal->id}/bill/print", ['mode' => 'none'])->assertOk();
    $orders[] = $meal;

    $yesterday = roomDay()->subDay()->toDateString();
    $ids = array_map(fn (PosOrder $order): int => $order->id, $orders);
    booking(function () use ($ids, $yesterday, $stay, $session): void {
        DB::table('pos_orders')->whereIn('id', $ids)->update(['business_date' => $yesterday]);
        DB::table('pos_bills')->whereIn('pos_order_id', $ids)->update(['business_date' => $yesterday]);
        DB::table('pos_payments')->whereIn('pos_bill_id', DB::table('pos_bills')->whereIn('pos_order_id', $ids)->select('id'))->update(['business_date' => $yesterday]);
        DB::table('package_redemptions')->update(['business_date' => $yesterday]);
        DB::table('pos_sessions')->where('id', $session->id)->update(['business_date' => $yesterday]);
        MealEntitlementSnapshot::query()->create(['property_id' => bookingIds()['property'], 'business_date' => $yesterday, 'reservation_id' => $stay->id, 'meal_period' => 'breakfast', 'covers' => 2]);
    });

    return ['yesterday' => $yesterday, 'manager' => $manager];
}

it('answers what the Pool Bar sold yesterday, who voided what and how many breakfasts were included vs taken (done when)', function (): void {
    $setup = billSetup();
    ['yesterday' => $day, 'manager' => $manager] = poolBarYesterday($setup);
    DB::table('outlet_user')->updateOrInsert(['tenant_id' => $manager->tenant_id, 'outlet_id' => $setup['terminal']->outlet_id, 'user_id' => $manager->id]);
    auth()->guard('web')->logout();
    $viewer = staffUser(DefaultRole::FnbManager);
    DB::table('outlet_user')->insert(['tenant_id' => $viewer->tenant_id, 'outlet_id' => $setup['terminal']->outlet_id, 'user_id' => $viewer->id]);
    $query = "from={$day}&to={$day}";

    // What it sold: net 550 + 630 + 200 = 1,380.00 (the meal-plan breakfast comes to nothing) over 4 bills and 7 covers; 70.00 of discounts.
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}"))->assertOk()
        ->assertSeeHtml('data-report-table="sales"')->assertSee('Pool Bar')->assertSeeHtml('data-figure="Net sales">1380.00')->assertSeeHtml('data-figure="Bills">4')
        ->assertSeeHtml('data-figure="Covers">7')->assertSeeHtml('data-figure="Spend per cover">197.14');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&by=item"))->assertOk()->assertSee('Naan')->assertSee('Virgin mojito')->assertSee('Items on a guest');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&by=method"))->assertOk()->assertSee('Card')->assertSee('Cash')->assertSee('Payments');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&by=waiter"))->assertOk()->assertSee('Net sales');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&by=hour"))->assertOk()->assertSeeHtml('data-report-table="sales"');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&by=category"))->assertOk();

    // Who voided what: the mojito, by the manager, with the reason and the wastage flag; and the discount.
    get(tenantUrl('sunrise', "/restaurant/reports/exceptions?{$query}"))->assertOk()
        ->assertSee('Voided item')->assertSee('1 × Virgin mojito')->assertSee($manager->name)->assertSee('Quality issue: Warm drink (wastage)')
        ->assertSee('Bill discount')->assertSee('10%')->assertSee('Regular guest');

    // Breakfasts: 2 included for the CP guest, 1 taken, 1 not taken.
    $meals = get(tenantUrl('sunrise', "/restaurant/reports/meal-plans?{$query}"))->assertOk()->assertSee('Breakfast');
    expect(preg_replace('/\s+/', '', strip_tags($meals->getContent())))->toContain($day.'Breakfast2110');

    // CSV export carries the same rows.
    $csv = get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}&export=csv"))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Pool Bar')->toContain('1380.00');
});

it('reports sessions, table turnover and an empty room-charge summary, and refuses other readers', function (): void {
    $setup = billSetup();
    ['yesterday' => $day] = poolBarYesterday($setup);
    auth()->guard('web')->logout();
    $viewer = staffUser(DefaultRole::FnbManager);
    DB::table('outlet_user')->insert(['tenant_id' => $viewer->tenant_id, 'outlet_id' => $setup['terminal']->outlet_id, 'user_id' => $viewer->id]);
    $query = "from={$day}&to={$day}";

    get(tenantUrl('sunrise', "/restaurant/reports/sessions?{$query}"))->assertOk()->assertSee('Cashier desk')->assertSee('Open')->assertSeeHtml('data-figure="Sessions">1');
    get(tenantUrl('sunrise', "/restaurant/reports/turnover?{$query}"))->assertOk()->assertSeeHtml('data-figure="Orders">4')->assertSee('T1');
    get(tenantUrl('sunrise', "/restaurant/reports/room-charges?{$query}"))->assertOk()->assertSee('No records in this range.');
    get(tenantUrl('sunrise', "/restaurant/reports/sales?from={$day}&to=2000-01-01"))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', '/restaurant/reports/nonsense'))->assertNotFound();

    auth()->guard('web')->logout();
    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', "/restaurant/reports/sales?{$query}"))->assertForbidden();
});
