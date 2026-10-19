<?php

/*
| Procurement reports (Step 5.8): open orders, pending receipts, vendor ledger, payables aging, purchase history and
| price variance. Date ranges are wide on purpose, so the tests do not depend on the business date.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Procurement\Actions\RecordVendorPayment;
use Modules\Procurement\DTOs\NewVendorPayment;
use Modules\Procurement\Enums\VendorPaymentMethod;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
});

const PR_RANGE = 'from=2020-01-01&to=2099-12-31';

/** A vendor with a part-received rice order billed 5 above the order price (override) and part paid, plus an untouched sugar order. */
function prScenario(): Vendor
{
    $vendor = pcVendor();
    $rice = pcItem('Rice');
    $sugar = pcItem('Sugar');
    $order = pcApprovedOrder([[$rice, '100', '160']], $vendor);
    pcApprovedOrder([[$sugar, '50', '118']], $vendor);
    pcReceive($order, [0 => '60']);
    pcApproveBill(pcBill(pcFresh($order), [0 => ['60', '165']], true));
    $accountant = pcUser(DefaultRole::Accountant);
    $bill = booking(fn () => VendorBill::query()->sole());
    booking(fn () => app(RecordVendorPayment::class)->handle(new NewVendorPayment(bookingIds()['property'], $vendor->id, VendorPaymentMethod::BankTransfer, '5000', $accountant->id, [$bill->id => '5000'])));

    return $vendor;
}

it('lists the orders still open and the goods still to arrive', function (): void {
    prScenario();
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/procurement/reports/open-orders'))->assertOk()->assertSeeHtml('data-report-table="open-orders"')->assertSee('Fresh Farm');
    get(tenantUrl('sunrise', '/procurement/reports/open-orders'))->assertSee('16,000.00')->assertSee('6,400.00')->assertSee('5,900.00');
    get(tenantUrl('sunrise', '/procurement/reports/pending-receipts'))->assertOk()->assertSee('Rice')->assertSee('Sugar')->assertSee('40 kg')->assertSee('50 kg');
});

it('shows a vendor ledger with bills, payments and the balance owed', function (): void {
    $vendor = prScenario();
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', "/procurement/reports/vendor-ledger?vendor={$vendor->id}&".PR_RANGE))->assertOk()->assertSee('9,900.00')->assertSee('5,000.00')->assertSee('4,900.00');
});

it('ages what is owed per vendor and shows the purchase history and price variance', function (): void {
    prScenario();
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/procurement/reports/vendor-aging?as_of=2099-12-31'))->assertOk()->assertSee('Fresh Farm')->assertSee('4,900.00');
    get(tenantUrl('sunrise', '/procurement/reports/purchase-history?'.PR_RANGE))->assertOk()->assertSee('Rice')->assertSee('9,600.00');
    get(tenantUrl('sunrise', '/procurement/reports/price-variance?'.PR_RANGE))->assertOk()->assertSee('300.00')->assertSee('5.00');
});

it('downloads a report as CSV, refuses unknown reports and keeps them to people with the permission', function (): void {
    prScenario();
    staffUser(DefaultRole::GeneralManager);

    $csv = get(tenantUrl('sunrise', '/procurement/reports/open-orders?export=csv'));
    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $content = $csv->streamedContent();
    expect($content)->toContain('Fresh Farm');
    expect($content)->not->toContain('<');
    get(tenantUrl('sunrise', '/procurement/reports/nonsense'))->assertNotFound();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/procurement/reports/open-orders'))->assertForbidden();
});
