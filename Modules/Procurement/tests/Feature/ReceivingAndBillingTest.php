<?php

/*
| Goods receipts, vendor bills with the 3-way match, payments and returns (Step 5.6). "Done when": a PO gets two partial
| receipts, a bill and a payment, and a bill that exceeds what was received is blocked.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\TaxCategory;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\Models\Item;
use Modules\Procurement\Actions\AllocateVendorPayment;
use Modules\Procurement\Actions\CancelVendorBill;
use Modules\Procurement\Actions\ReceiveGoods;
use Modules\Procurement\Actions\RecordVendorPayment;
use Modules\Procurement\Actions\ReturnToVendor;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SaveVendorBill;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\Actions\SubmitVendorBill;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\DTOs\NewVendorPayment;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Enums\VendorPaymentMethod;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
});

function rbHeld(Item $item): string
{
    return booking(fn (): string => app(StockMovementContract::class)->balance(pcStore()->id, $item->id)->quantity);
}

function rbStatus(PurchaseOrder $order): PurchaseOrderStatus
{
    return booking(fn (): PurchaseOrderStatus => PurchaseOrder::query()->findOrFail($order->id)->status);
}

it('receives an order in two parts, into stock at the order price', function (): void {
    $rice = pcItem('Rice');
    $sugar = pcItem('Sugar');
    $order = pcApprovedOrder([[$rice, '100', '160'], [$sugar, '50', '118']]);

    $first = pcReceive($order, [0 => '60', 1 => '50']);
    expect($first->receipt_no)->toStartWith('GRN-')->and($first->total_value)->toBe('15500.00')->and(rbHeld($rice))->toBe('60.0000')->and(rbHeld($sugar))->toBe('50.0000')->and(rbStatus($order))->toBe(PurchaseOrderStatus::Partial);

    $second = pcReceive(pcFresh($order), [0 => '40']);
    expect($second->total_value)->toBe('6400.00')->and(rbHeld($rice))->toBe('100.0000')->and(rbStatus($order))->toBe(PurchaseOrderStatus::Received)
        ->and(booking(fn (): string => app(StockMovementContract::class)->balance(pcStore()->id, $rice->id)->avgCost))->toBe('160.0000')
        ->and(fn () => pcReceive(pcFresh($order), [0 => '1']))->toThrow(ProcurementRuleViolated::class, 'not yet complete');
});

it('records rejected goods without stocking them, and needs a reason', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    $receive = fn (array $line) => booking(fn () => app(ReceiveGoods::class)->handle($order, [['purchase_order_line_id' => $order->lines[0]->id, ...$line]], 1));

    expect(fn () => $receive(['accepted_quantity' => '90', 'rejected_quantity' => '10']))->toThrow(ProcurementRuleViolated::class, 'reason')
        ->and(fn () => $receive(['accepted_quantity' => '-1']))->toThrow(ProcurementRuleViolated::class, 'negative')
        ->and(fn () => $receive(['accepted_quantity' => '', 'rejected_quantity' => '']))->toThrow(ProcurementRuleViolated::class, 'Enter what was received');

    $receipt = $receive(['accepted_quantity' => '90', 'rejected_quantity' => '10', 'reject_reason' => 'Wet sacks']);
    expect($receipt->lines->sole()->rejected_quantity)->toBe('10.0000')->and(rbHeld($rice))->toBe('90.0000')->and($receipt->total_value)->toBe('14400.00')
        ->and(rbStatus($order))->toBe(PurchaseOrderStatus::Partial);
});

it('stops over-receipt unless the setting allows it', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);

    expect(fn () => pcReceive($order, [0 => '101']))->toThrow(ProcurementRuleViolated::class, 'at most 100');

    booking(fn () => app(Settings::class)->set('procurement.over_receipt_percent', '5', bookingIds()['property']));
    pcReceive($order, [0 => '105']);

    expect(rbHeld($rice))->toBe('105.0000')->and(rbStatus($order))->toBe(PurchaseOrderStatus::Received);
});

it('blocks a bill that exceeds what was received (the 3-way match)', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    pcReceive($order, [0 => '60']);

    expect(fn () => pcBill(pcFresh($order), [0 => ['61', '160']]))->toThrow(ProcurementRuleViolated::class, '3-way match')
        ->and(booking(fn (): int => VendorBill::query()->count()))->toBe(0);

    $bill = pcBill(pcFresh($order), [0 => ['60', '160']]);
    expect($bill->total)->toBe('9600.00')->and($bill->lines->sole()->grni_amount)->toBe('9600.00')->and($bill->status)->toBe(VendorBillStatus::Draft);
});

it('counts earlier bills and returns against the quantity that can still be billed', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    $receipt = pcReceive($order, [0 => '100']);
    pcApproveBill(pcBill(pcFresh($order), [0 => ['70', '160']]));
    booking(fn () => app(ReturnToVendor::class)->handle($receipt, [['goods_receipt_line_id' => $receipt->lines[0]->id, 'quantity' => '10']], 'Damaged', 1));

    // 100 received - 10 returned - 70 billed = 20 left.
    expect(fn () => pcBill(pcFresh($order), [0 => ['21', '160']]))->toThrow(ProcurementRuleViolated::class, '3-way match');

    $rest = pcApproveBill(pcBill(pcFresh($order), [0 => ['20', '160']]));
    expect($rest->status)->toBe(VendorBillStatus::Approved)->and(rbStatus($order))->toBe(PurchaseOrderStatus::Closed);
});

it('needs the override for a price above the order price beyond the tolerance', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    pcReceive($order, [0 => '100']);

    expect(fn () => pcBill(pcFresh($order), [0 => ['100', '165']]))->toThrow(ProcurementRuleViolated::class, 'above the order price');

    $within = pcBill(pcFresh($order), [0 => ['10', '163']]);
    expect($within->match_overridden)->toBeFalse();

    $overridden = pcBill(pcFresh($order), [0 => ['10', '165']], override: true);
    expect($overridden->match_overridden)->toBeTrue()->and($overridden->total)->toBe('1650.00')->and($overridden->lines->sole()->grni_amount)->toBe('1600.00');
});

it('prices bill tax from the order line and books service bills with a class', function (): void {
    $rice = pcItem('Rice');
    $category = booking(fn (): TaxCategory => TaxCategory::query()->firstOrFail());
    $vendor = pcVendor();
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $order = booking(fn (): PurchaseOrder => app(SavePurchaseOrder::class)->handle(null, new NewPurchaseOrder(bookingIds()['property'], $vendor->id, pcStore()->id, [['item_id' => $rice->id, 'quantity' => '10', 'unit_price' => '100', 'tax_category_id' => $category->id]], $buyer->id)));
    $order = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle($order, $buyer->id));
    booking(fn () => app(ApprovalEngine::class)->approve((int) $order->approval_request_id, pcUser(DefaultRole::Accountant)->id));
    $order = booking(fn (): PurchaseOrder => PurchaseOrder::query()->with('lines')->findOrFail($order->id));
    pcReceive($order, [0 => '10']);

    $bill = pcBill($order, [0 => ['10', '100']]);
    expect($bill->subtotal)->toBe('1000.00')->and($bill->tax_total)->toBe($order->tax_total)->and($bill->total)->toBe($order->total);

    $service = booking(fn (): VendorBill => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $vendor->id, VendorBillKind::Service, [['description' => 'Pump service', 'expense_class' => 'repairs', 'quantity' => '1', 'unit_price' => '12000']], $buyer->id)));
    expect($service->total)->toBe('12000.00')->and($service->lines->sole()->expense_class->value)->toBe('repairs')->and($service->due_on->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and(fn () => booking(fn () => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $vendor->id, VendorBillKind::Service, [['description' => '', 'unit_price' => '5']], $buyer->id))))->toThrow(ProcurementRuleViolated::class, 'description')
        ->and(fn () => booking(fn () => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $vendor->id, VendorBillKind::Purchase, [['purchase_order_line_id' => 1, 'quantity' => '1', 'unit_price' => '1']], $buyer->id))))->toThrow(ProcurementRuleViolated::class, 'order');
});

it('refuses a bill for an order from another vendor or with nothing received', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '10', '100']]);
    $other = pcVendor('Other Ltd', 'o@x.example');
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $line = [['purchase_order_line_id' => $order->lines[0]->id, 'quantity' => '1', 'unit_price' => '100']];

    expect(fn () => booking(fn () => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $order->vendor_id, VendorBillKind::Purchase, $line, $buyer->id, $order->id))))->toThrow(ProcurementRuleViolated::class, 'received nothing');

    pcReceive($order, [0 => '10']);
    expect(fn () => booking(fn () => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $other->id, VendorBillKind::Purchase, $line, $buyer->id, $order->id))))->toThrow(ProcurementRuleViolated::class, 'another vendor');
});

it('approves a bill, edits only drafts, and cancels what is not yet approved', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    pcReceive($order, [0 => '100']);
    $bill = pcBill(pcFresh($order), [0 => ['40', '160']]);
    $buyer = pcUser(DefaultRole::ProcurementOfficer);

    $submitted = booking(fn (): VendorBill => app(SubmitVendorBill::class)->handle($bill, $buyer->id));
    expect($submitted->status)->toBe(VendorBillStatus::Pending)
        ->and(fn () => booking(fn () => app(SubmitVendorBill::class)->handle($submitted, $buyer->id)))->toThrow(ProcurementRuleViolated::class)
        ->and(fn () => pcBill(pcFresh($order), [0 => ['61', '160']]))->toThrow(ProcurementRuleViolated::class, '3-way match');

    booking(fn () => app(CancelVendorBill::class)->handle($submitted, $buyer->id));
    expect(booking(fn (): VendorBillStatus => VendorBill::query()->findOrFail($bill->id)->status))->toBe(VendorBillStatus::Cancelled);

    // The cancelled bill no longer uses up the quantity.
    $approved = pcApproveBill(pcBill(pcFresh($order), [0 => ['100', '160']]));
    expect($approved->status)->toBe(VendorBillStatus::Approved)->and($approved->approved_at)->not->toBeNull()
        ->and(fn () => booking(fn () => app(CancelVendorBill::class)->handle($approved, $buyer->id)))->toThrow(ProcurementRuleViolated::class, 'not yet approved')
        ->and(fn () => booking(fn () => app(SaveVendorBill::class)->handle($approved, new NewVendorBill(bookingIds()['property'], $order->vendor_id, VendorBillKind::Purchase, [['purchase_order_line_id' => $order->lines[0]->id, 'quantity' => '1', 'unit_price' => '160']], 1, $order->id))))->toThrow(ProcurementRuleViolated::class, 'only a draft');
});

it('returns a rejected bill to a draft', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '10', '100']]);
    pcReceive($order, [0 => '10']);
    $bill = booking(fn (): VendorBill => app(SubmitVendorBill::class)->handle(pcBill(pcFresh($order), [0 => ['10', '100']]), pcUser(DefaultRole::ProcurementOfficer)->id));
    booking(fn () => app(ApprovalEngine::class)->reject((int) $bill->approval_request_id, pcUser(DefaultRole::Accountant)->id, 'Wrong invoice'));

    expect(booking(fn (): VendorBillStatus => VendorBill::query()->findOrFail($bill->id)->status))->toBe(VendorBillStatus::Draft);
});

it('pays bills in parts, refuses overpayment, and keeps the rest as an advance to apply later', function (): void {
    $rice = pcItem('Rice');
    $vendor = pcVendor();
    $order = pcApprovedOrder([[$rice, '100', '160']], $vendor);
    pcReceive($order, [0 => '100']);
    $bill = pcApproveBill(pcBill(pcFresh($order), [0 => ['100', '160']]));
    $accountant = pcUser(DefaultRole::Accountant);
    $pay = fn (string $amount, array $allocations = []) => booking(fn (): VendorPayment => app(RecordVendorPayment::class)->handle(new NewVendorPayment(bookingIds()['property'], $vendor->id, VendorPaymentMethod::BankTransfer, $amount, $accountant->id, $allocations)));
    $reload = fn (): VendorBill => booking(fn (): VendorBill => VendorBill::query()->findOrFail($bill->id));

    expect(fn () => $pay('20000', [$bill->id => '16001']))->toThrow(ProcurementRuleViolated::class, 'left to pay')
        ->and(fn () => $pay('100', [$bill->id => '200']))->toThrow(ProcurementRuleViolated::class, 'left to allocate')
        ->and(fn () => $pay('0'))->toThrow(ProcurementRuleViolated::class, 'more than zero')
        ->and(booking(fn (): int => VendorPayment::query()->count()))->toBe(0);

    $first = $pay('6000', [$bill->id => '6000']);
    expect($first->payment_no)->toStartWith('VP-')->and($first->allocated_amount)->toBe('6000.00')->and($reload()->status)->toBe(VendorBillStatus::Partial)->and($reload()->paid_amount)->toBe('6000.00');

    $advance = $pay('12000');
    expect($advance->allocated_amount)->toBe('0.00');
    booking(fn () => app(AllocateVendorPayment::class)->handle($advance, [$bill->id => '10000']));

    expect($reload()->status)->toBe(VendorBillStatus::Paid)->and($reload()->paid_amount)->toBe('16000.00')->and(booking(fn (): string => VendorPayment::query()->findOrFail($advance->id)->allocated_amount))->toBe('10000.00')
        ->and(fn () => booking(fn () => app(AllocateVendorPayment::class)->handle($advance, [$bill->id => '1'])))->toThrow(ProcurementRuleViolated::class, 'approved bills');
});

it('refuses to pay another vendor\'s bill or a bill that is not approved', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '10', '100']]);
    pcReceive($order, [0 => '10']);
    $draft = pcBill(pcFresh($order), [0 => ['10', '100']]);
    $other = pcVendor('Other Ltd', 'o@x.example');
    $try = fn (int $vendorId, int $billId) => booking(fn () => app(RecordVendorPayment::class)->handle(new NewVendorPayment(bookingIds()['property'], $vendorId, VendorPaymentMethod::Cash, '100', 1, [$billId => '100'])));

    expect(fn () => $try($order->vendor_id, $draft->id))->toThrow(ProcurementRuleViolated::class, 'approved bills');

    $approved = pcApproveBill($draft);
    expect(fn () => $try($other->id, $approved->id))->toThrow(ProcurementRuleViolated::class, 'this vendor');
});

it('returns goods with a debit note, limited to what was accepted, and takes them out of stock', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    $receipt = pcReceive($order, [0 => '100']);
    $lineId = $receipt->lines[0]->id;
    $return = fn (string $quantity, string $reason = 'Damaged') => booking(fn () => app(ReturnToVendor::class)->handle($receipt, [['goods_receipt_line_id' => $lineId, 'quantity' => $quantity]], $reason, 1));

    expect(fn () => $return('5', ' '))->toThrow(ProcurementRuleViolated::class, 'reason')
        ->and(fn () => $return('101'))->toThrow(ProcurementRuleViolated::class, 'can still be returned')
        ->and(fn () => $return('0'))->toThrow(ProcurementRuleViolated::class, 'Enter what is being returned');

    $first = $return('10');
    expect($first->return_no)->toStartWith('RTV-')->and($first->debit_note_no)->toStartWith('DN-')->and($first->total_value)->toBe('1600.00')->and(rbHeld($rice))->toBe('90.0000')
        ->and($first->lines->sole()->grni_value)->toBe('1600.00')->and($first->lines->sole()->billed_value)->toBe('0.00')
        ->and(fn () => $return('91'))->toThrow(ProcurementRuleViolated::class, 'can still be returned');

    pcApproveBill(pcBill(pcFresh($order), [0 => ['90', '160']]));
    $billed = $return('20');
    expect($billed->lines->sole()->billed_value)->toBe('3200.00')->and($billed->lines->sole()->grni_value)->toBe('0.00')->and(rbHeld($rice))->toBe('70.0000');
});

it('takes receipts, bills, payments and returns through the screens', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}"))->assertOk()->assertSeeHtml('data-receive-goods')->assertDontSeeHtml('data-enter-bill');
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}/receive"))->assertOk()->assertSeeHtml('data-receipt-form');
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/receive"), ['lines' => [$order->lines[0]->id => ['accepted_quantity' => '500']]])->assertSessionHas('error');
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/receive"), ['delivery_note' => 'DN-9', 'lines' => [$order->lines[0]->id => ['accepted_quantity' => '60', 'rejected_quantity' => '2', 'reject_reason' => 'Wet']]])->assertSessionHas('success');

    $receipt = booking(fn (): GoodsReceipt => GoodsReceipt::query()->sole());
    get(tenantUrl('sunrise', '/procurement/receipts'))->assertOk()->assertSeeHtml('data-receipt="'.$receipt->receipt_no.'"');
    get(tenantUrl('sunrise', "/procurement/receipts/{$receipt->id}"))->assertOk()->assertSee('9,600.00')->assertSee('Wet');

    get(tenantUrl('sunrise', "/procurement/bills/new?order={$order->id}"))->assertOk()->assertSeeHtml('data-bill-lines');
    post(tenantUrl('sunrise', '/procurement/bills'), ['kind' => 'purchase', 'vendor_id' => $order->vendor_id, 'purchase_order_id' => $order->id, 'lines' => [['purchase_order_line_id' => $order->lines[0]->id, 'quantity' => '61', 'unit_price' => '160']]])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/procurement/bills'), ['kind' => 'purchase', 'vendor_id' => $order->vendor_id, 'purchase_order_id' => $order->id, 'vendor_reference' => 'INV-77', 'lines' => [['purchase_order_line_id' => $order->lines[0]->id, 'quantity' => '60', 'unit_price' => '160']]])->assertSessionHas('success');

    $bill = booking(fn (): VendorBill => VendorBill::query()->sole());
    get(tenantUrl('sunrise', "/procurement/bills/{$bill->id}"))->assertOk()->assertSee('9,600.00')->assertSeeHtml('data-submit-bill');
    get(tenantUrl('sunrise', "/procurement/bills/{$bill->id}/edit"))->assertOk();
    post(tenantUrl('sunrise', "/procurement/bills/{$bill->id}/submit"))->assertSessionHas('success');
    booking(fn () => app(ApprovalEngine::class)->approve((int) booking(fn () => VendorBill::query()->findOrFail($bill->id))->approval_request_id, pcUser(DefaultRole::Accountant)->id));
    get(tenantUrl('sunrise', "/procurement/bills/{$bill->id}"))->assertOk()->assertSeeHtml('data-pay');
    get(tenantUrl('sunrise', '/procurement/bills'))->assertOk()->assertSeeHtml('data-bill="'.$bill->bill_no.'"');

    get(tenantUrl('sunrise', "/procurement/payments/new?vendor={$order->vendor_id}&bill={$bill->id}"))->assertOk()->assertSeeHtml('data-open-bills');
    post(tenantUrl('sunrise', '/procurement/payments'), ['vendor_id' => $order->vendor_id, 'method' => 'cheque', 'amount' => '0'])->assertSessionHasErrors('amount');
    post(tenantUrl('sunrise', '/procurement/payments'), ['vendor_id' => $order->vendor_id, 'method' => 'cheque', 'amount' => '10000', 'reference' => 'CHQ-1', 'allocations' => [$bill->id => '9600']])->assertSessionHas('success');
    $payment = booking(fn (): VendorPayment => VendorPayment::query()->sole());
    get(tenantUrl('sunrise', "/procurement/payments/{$payment->id}"))->assertOk()->assertSeeHtml('data-allocations')->assertSee('400.00');
    get(tenantUrl('sunrise', '/procurement/payments'))->assertOk()->assertSeeHtml('data-payment="'.$payment->payment_no.'"');
    get(tenantUrl('sunrise', "/procurement/vendors/{$order->vendor_id}"))->assertOk()->assertSeeHtml('data-advances')->assertSeeHtml('data-pay-vendor');

    get(tenantUrl('sunrise', "/procurement/receipts/{$receipt->id}/return"))->assertOk()->assertSeeHtml('data-return-form');
    post(tenantUrl('sunrise', "/procurement/receipts/{$receipt->id}/return"), ['reason' => '', 'lines' => [$receipt->lines[0]->id => '2']])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', "/procurement/receipts/{$receipt->id}/return"), ['reason' => 'Damaged', 'lines' => [$receipt->lines[0]->id => '2']])->assertSessionHas('success');
    $return = booking(fn (): PurchaseReturn => PurchaseReturn::query()->sole());
    get(tenantUrl('sunrise', "/procurement/returns/{$return->id}"))->assertOk()->assertSee($return->debit_note_no)->assertSee('320.00');
    get(tenantUrl('sunrise', '/procurement/returns'))->assertOk()->assertSeeHtml('data-return="'.$return->return_no.'"');
});

it('lets a service bill be entered through the screen', function (): void {
    $vendor = pcVendor();
    staffUser(DefaultRole::Accountant);

    get(tenantUrl('sunrise', '/procurement/bills/new'))->assertOk()->assertSeeHtml('data-bill-form');
    post(tenantUrl('sunrise', '/procurement/bills'), ['kind' => 'service', 'vendor_id' => $vendor->id, 'lines' => [['description' => 'Pest control', 'expense_class' => 'administrative', 'quantity' => '1', 'unit_price' => '3500']]])->assertSessionHas('success');

    expect(booking(fn (): string => VendorBill::query()->sole()->total))->toBe('3500.00');
});

it('keeps receiving, billing, paying and returning to the people who hold the permissions', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '10', '100']]);
    $receipt = pcReceive($order, [0 => '10']);
    $bill = pcBill(pcFresh($order), [0 => ['10', '100']]);

    staffUser(DefaultRole::FrontDeskAgent);
    foreach (['/procurement/receipts', "/procurement/receipts/{$receipt->id}", "/procurement/orders/{$order->id}/receive", '/procurement/bills', "/procurement/bills/{$bill->id}", '/procurement/bills/new', '/procurement/payments', '/procurement/payments/new', '/procurement/returns', "/procurement/receipts/{$receipt->id}/return"] as $url) {
        get(tenantUrl('sunrise', $url))->assertForbidden();
    }
    post(tenantUrl('sunrise', "/procurement/bills/{$bill->id}/submit"))->assertForbidden();
    post(tenantUrl('sunrise', '/procurement/payments'), [])->assertForbidden();

    staffUser(DefaultRole::StoreKeeper);
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}/receive"))->assertForbidden();
    get(tenantUrl('sunrise', '/procurement/bills'))->assertForbidden();
    get(tenantUrl('sunrise', "/procurement/receipts/{$receipt->id}/return"))->assertOk();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/procurement/bills'))->assertOk()->assertDontSeeHtml('data-new-bill');
    get(tenantUrl('sunrise', "/procurement/bills/{$bill->id}"))->assertOk()->assertDontSeeHtml('data-submit-bill');
    post(tenantUrl('sunrise', "/procurement/bills/{$bill->id}/cancel"))->assertForbidden();
    get(tenantUrl('sunrise', '/procurement/payments/new'))->assertForbidden();
});
