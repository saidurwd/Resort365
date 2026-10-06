<?php

/*
| Times are stored in UTC and shown in the property's timezone (ARCHITECTURE §12 rule 9).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\DisplayTimezone;
use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\PosBill;

use function Pest\Laravel\get;

require_once __DIR__.'/../../Modules/Restaurant/tests/Support/pos-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

it('shows stored UTC times in the current property\'s timezone, and in UTC without one', function (): void {
    $noon = CarbonImmutable::parse('2026-10-05 12:00:00', 'UTC');
    $propertyId = bookingIds()['property'];

    // No property chosen: the application's timezone (UTC).
    expect(app(DisplayTimezone::class)->format($noon, 'H:i'))->toBe('12:00');

    // A property chosen (Asia/Dhaka, UTC+6): both Carbon classes convert (the macro views use), and the stored value is untouched.
    app(PropertyContext::class)->restrictTo([$propertyId => 'Sunrise'], $propertyId);
    $stored = Carbon::parse('2026-10-05 23:30:00', 'UTC');
    expect(app(DisplayTimezone::class)->format($noon, 'H:i'))->toBe('18:00')
        ->and(app(DisplayTimezone::class)->format($stored, 'Y-m-d H:i'))->toBe('2026-10-06 05:30')
        ->and($stored->format('Y-m-d H:i'))->toBe('2026-10-05 23:30')
        ->and(app(DisplayTimezone::class)->name())->toBe('Asia/Dhaka');
});

it('can be set to a property for a POS or kitchen screen', function (): void {
    $display = app(DisplayTimezone::class);
    $display->use(bookingIds()['property']);

    expect($display->name())->toBe('Asia/Dhaka')
        ->and(app(DisplayTimezone::class)->format(CarbonImmutable::parse('2026-10-05 20:00:00', 'UTC'), 'H:i'))->toBe('02:00');

    $display->use(999999);
    expect($display->name())->toBe('UTC');
});

it('prints kitchen tickets and receipts in the property\'s time', function (): void {
    $setup = billSetup();
    $cashier = posStaff(DefaultRole::OutletCashier, '2222', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($cashier, '2222')->assertRedirect();
    openSession($setup['terminal'], $cashier->id);
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan']])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $kot = booking(fn (): Kot => Kot::query()->sole());
    booking(fn () => DB::table('kots')->where('id', $kot->id)->update(['fired_at' => '2026-10-05 12:05:00']));

    get(tenantUrl('sunrise', "/pos/kots/{$kot->id}/print"))->assertOk()->assertSee('05 Oct 18:05');

    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->json('billing.bills.0');
    booking(fn () => DB::table('pos_bills')->where('id', $bill['id'])->update(['printed_at' => '2026-10-05 15:45:00']));
    get(tenantUrl('sunrise', "/pos/bills/{$bill['id']}/print"))->assertOk()->assertSee('21:45');
    expect(booking(fn () => PosBill::query()->findOrFail($bill['id'])->printed_at->format('H:i')))->toBe('15:45');
});
