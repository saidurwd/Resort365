<?php

/*
| Requisitions, reorder suggestions, RFQs and purchase orders (Step 5.5). "Done when": a requisition becomes an
| approved order, emailed to the vendor as a PDF.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Models\TaxCategory;
use Modules\Inventory\Contracts\ReorderSuggestions;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Procurement\Actions\AwardRfq;
use Modules\Procurement\Actions\CancelPurchaseOrder;
use Modules\Procurement\Actions\CreateOrderFromRequisition;
use Modules\Procurement\Actions\CreateRequisitionFromSuggestions;
use Modules\Procurement\Actions\CreateRfq;
use Modules\Procurement\Actions\RecordQuotation;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SavePurchaseRequisition;
use Modules\Procurement\Actions\SaveVendorContact;
use Modules\Procurement\Actions\SendPurchaseOrder;
use Modules\Procurement\Actions\SendRfq;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\Actions\WithdrawPurchaseRequisition;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\QuotationStatus;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorQuotation;
use Modules\Procurement\Notifications\VendorMessage;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
    Notification::fake();
});

/** @param  list<array{item_id: int, quantity: string, estimated_unit_cost?: string|null}>  $lines */
function pcRequisition(array $lines, ?int $userId = null): PurchaseRequisition
{
    $userId ??= pcUser(DefaultRole::StoreKeeper)->id;

    return booking(fn (): PurchaseRequisition => app(SavePurchaseRequisition::class)->handle(bookingIds()['property'], null, $lines, $userId, notes: 'Weekend'));
}

/** @param  list<array{item_id: int, quantity: string, estimated_unit_cost?: string|null}>  $lines */
function pcApprovedRequisition(array $lines): PurchaseRequisition
{
    $requisition = pcRequisition($lines);
    booking(fn () => app(ApprovalEngine::class)->approve((int) $requisition->approval_request_id, pcUser(DefaultRole::FnbManager)->id));

    return booking(fn (): PurchaseRequisition => PurchaseRequisition::query()->findOrFail($requisition->id));
}

/**
 * @param  list<array{0: Item, 1: string, 2: string}>  $pairs
 * @return list<array{item_id: int, quantity: string, unit_price: string}>
 */
function pcOrderLines(array $pairs): array
{
    return collect($pairs)->map(fn (array $pair): array => ['item_id' => $pair[0]->id, 'quantity' => $pair[1], 'unit_price' => $pair[2]])->all();
}

/** @param  list<array{item_id: int, quantity: string, unit_price: string}>  $lines */
function pcOrder(array $lines, ?Vendor $vendor = null, ?int $requisitionId = null): PurchaseOrder
{
    $vendor ??= pcVendor();

    return booking(fn (): PurchaseOrder => app(SavePurchaseOrder::class)->handle(null, new NewPurchaseOrder(bookingIds()['property'], $vendor->id, pcStore()->id, $lines, pcUser(DefaultRole::ProcurementOfficer)->id, requisitionId: $requisitionId)));
}

it('values a requisition, waits for the approver and then is ready to order', function (): void {
    $rice = pcItem('Rice', cost: '100');
    $requisition = pcRequisition([['item_id' => $rice->id, 'quantity' => '10', 'estimated_unit_cost' => '120']]);

    expect($requisition->status)->toBe(PurchaseRequisitionStatus::Pending)->and($requisition->requisition_no)->toStartWith('PR-')->and($requisition->estimated_value)->toBe('1200.00');

    $keeper = pcUser(DefaultRole::StoreKeeper);
    expect(fn () => booking(fn () => app(ApprovalEngine::class)->approve((int) $requisition->approval_request_id, $keeper->id)))->toThrow(ApprovalRuleViolated::class);

    $approved = pcApprovedRequisition([['item_id' => pcItem('Sugar')->id, 'quantity' => '4']]);
    expect($approved->status)->toBe(PurchaseRequisitionStatus::Approved)->and($approved->estimated_value)->toBe('400.00');
});

it('refuses a requisition with no items, repeated items, a bad quantity or an unknown item', function (): void {
    $rice = pcItem('Rice');
    $save = fn (array $lines) => pcRequisition($lines);

    expect(fn () => $save([]))->toThrow(ProcurementRuleViolated::class, 'at least one')
        ->and(fn () => $save([['item_id' => $rice->id, 'quantity' => '1'], ['item_id' => $rice->id, 'quantity' => '2']]))->toThrow(ProcurementRuleViolated::class, 'once')
        ->and(fn () => $save([['item_id' => $rice->id, 'quantity' => '0']]))->toThrow(ProcurementRuleViolated::class, 'above zero')
        ->and(fn () => $save([['item_id' => 999999, 'quantity' => '1']]))->toThrow(ProcurementRuleViolated::class, 'Unknown item');
});

it('marks a rejected requisition and lets it be withdrawn only while open', function (): void {
    $rice = pcItem('Rice');
    $keeper = pcUser(DefaultRole::StoreKeeper);
    $rejected = pcRequisition([['item_id' => $rice->id, 'quantity' => '5']], $keeper->id);
    $manager = pcUser(DefaultRole::FnbManager);
    booking(fn () => app(ApprovalEngine::class)->reject((int) $rejected->approval_request_id, $manager->id, 'Not needed'));

    expect(booking(fn (): PurchaseRequisitionStatus => PurchaseRequisition::query()->findOrFail($rejected->id)->status))->toBe(PurchaseRequisitionStatus::Rejected)
        ->and(fn () => booking(fn () => app(WithdrawPurchaseRequisition::class)->handle($rejected, $keeper->id)))->toThrow(ProcurementRuleViolated::class);

    $open = pcRequisition([['item_id' => $rice->id, 'quantity' => '5']], $keeper->id);
    booking(fn () => app(WithdrawPurchaseRequisition::class)->handle($open, $keeper->id));
    expect(booking(fn (): PurchaseRequisitionStatus => PurchaseRequisition::query()->findOrFail($open->id)->status))->toBe(PurchaseRequisitionStatus::Cancelled);
});

it('suggests what is at or below its reorder level and raises a requisition from it', function (): void {
    $store = pcStore();
    $low = pcItem('Eggs', reorder: '100', cost: '14');
    $fine = pcItem('Salt', reorder: '10', cost: '40');
    $none = pcItem('Water');
    pcStock($store, $low, '60', '14');
    pcStock($store, $fine, '50', '40');
    pcStock($store, $none, '5', '10');

    $suggested = booking(fn (): array => app(ReorderSuggestions::class)->forProperty(bookingIds()['property']));
    expect(collect($suggested)->pluck('itemId')->all())->toBe([$low->id])->and($suggested[0]->onHand)->toBe('60.0000')->and($suggested[0]->suggestedQuantity)->toBe('140.0000')->and($suggested[0]->unitCost)->toBe('14.0000');

    $keeper = pcUser(DefaultRole::StoreKeeper);
    $requisition = booking(fn (): PurchaseRequisition => app(CreateRequisitionFromSuggestions::class)->handle(bookingIds()['property'], [$low->id], $keeper->id, [$low->id => '200']));
    $line = $requisition->lines->sole();

    expect($requisition->source->value)->toBe('reorder')->and((string) $line->quantity)->toBe('200.0000')->and($requisition->estimated_value)->toBe('2800.00')
        ->and(fn () => booking(fn () => app(CreateRequisitionFromSuggestions::class)->handle(bookingIds()['property'], [$fine->id], $keeper->id)))->toThrow(ProcurementRuleViolated::class, 'reorder level');
});

it('uses the par level for a suggestion when the store has one', function (): void {
    $store = pcStore();
    $item = pcItem('Eggs', reorder: '100');
    pcStock($store, $item, '40');
    booking(fn () => ItemStoreLevel::query()->create(['item_id' => $item->id, 'store_id' => $store->id, 'par_level' => '500', 'reorder_level' => '80']));

    $suggested = booking(fn (): array => app(ReorderSuggestions::class)->forProperty(bookingIds()['property']));

    expect($suggested[0]->suggestedQuantity)->toBe('460.0000')->and($suggested[0]->reorderLevel)->toBe('80.0000');
});

it('runs an RFQ: two vendors answer, the cheapest complete quotation wins and becomes an order', function (): void {
    $chicken = pcItem('Chicken');
    $hilsa = pcItem('Hilsa');
    $farm = pcVendor('Fresh Farm');
    $bay = pcVendor('Bay Fisheries', 'sales@bay.example');
    $requisition = pcApprovedRequisition([['item_id' => $chicken->id, 'quantity' => '40'], ['item_id' => $hilsa->id, 'quantity' => '20']]);
    $buyer = pcUser(DefaultRole::ProcurementOfficer);

    expect(fn () => booking(fn () => app(CreateRfq::class)->handle($requisition, [$farm->id], $buyer->id)))->toThrow(ProcurementRuleViolated::class, 'at least two');

    $rfq = booking(fn (): Rfq => app(CreateRfq::class)->handle($requisition, [$farm->id, $bay->id], $buyer->id, '2026-07-01'));
    expect($rfq->rfq_no)->toStartWith('RFQ-')->and($rfq->status)->toBe(RfqStatus::Draft)
        ->and(fn () => booking(fn () => app(RecordQuotation::class)->handle($rfq, $farm->id, [['item_id' => $chicken->id, 'unit_price' => '275']])))->toThrow(ProcurementRuleViolated::class, 'out with the vendors');

    $sent = booking(fn (): array => app(SendRfq::class)->handle($rfq));
    expect($sent['skipped'])->toBe([])->and($sent['rfq']->status)->toBe(RfqStatus::Sent);
    Notification::assertSentOnDemandTimes(VendorMessage::class, 2);

    $rfq = booking(fn (): Rfq => Rfq::query()->findOrFail($rfq->id));
    $quote = fn ($vendor, array $prices) => booking(fn (): VendorQuotation => app(RecordQuotation::class)->handle($rfq, $vendor->id, $prices, 'Q'));
    $farmQuote = $quote($farm, [['item_id' => $chicken->id, 'unit_price' => '275'], ['item_id' => $hilsa->id, 'unit_price' => '1850']]);
    $bayQuote = $quote($bay, [['item_id' => $chicken->id, 'unit_price' => '290'], ['item_id' => $hilsa->id, 'unit_price' => '1790', 'lead_days' => 2]]);

    expect($farmQuote->total)->toBe('48000.00')->and($bayQuote->total)->toBe('47400.00')
        ->and(fn () => booking(fn () => app(RecordQuotation::class)->handle($rfq, $bay->id, [['item_id' => 999999, 'unit_price' => '1']])))->toThrow(ProcurementRuleViolated::class, 'does not ask')
        ->and(fn () => booking(fn () => app(RecordQuotation::class)->handle($rfq, $bay->id, [['item_id' => $chicken->id, 'unit_price' => '']])))->toThrow(ProcurementRuleViolated::class, 'at least one price');

    $order = booking(fn (): PurchaseOrder => app(AwardRfq::class)->handle($rfq, $bayQuote, pcStore()->id, $buyer->id));

    expect($order->vendor_id)->toBe($bay->id)->and($order->total)->toBe('47400.00')->and($order->status)->toBe(PurchaseOrderStatus::Draft)->and($order->vendor_quotation_id)->toBe($bayQuote->id)
        ->and($order->purchase_requisition_id)->toBe($requisition->id)->and($order->lines)->toHaveCount(2)
        ->and(booking(fn (): RfqStatus => Rfq::query()->findOrFail($rfq->id)->status))->toBe(RfqStatus::Awarded)
        ->and(booking(fn (): array => VendorQuotation::query()->orderBy('id')->get()->map(fn (VendorQuotation $quotation): string => $quotation->status->value)->all()))->toBe([QuotationStatus::Rejected->value, QuotationStatus::Selected->value])
        ->and(booking(fn (): PurchaseRequisitionStatus => PurchaseRequisition::query()->findOrFail($requisition->id)->status))->toBe(PurchaseRequisitionStatus::Ordered);
});

it('refuses to award a quotation that leaves items out, and to send an RFQ nobody can receive', function (): void {
    $chicken = pcItem('Chicken');
    $hilsa = pcItem('Hilsa');
    $silent = pcVendor('No Mail Ltd', null);
    $silentToo = pcVendor('Also No Mail', null);
    $requisition = pcApprovedRequisition([['item_id' => $chicken->id, 'quantity' => '4'], ['item_id' => $hilsa->id, 'quantity' => '2']]);
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $rfq = booking(fn (): Rfq => app(CreateRfq::class)->handle($requisition, [$silent->id, $silentToo->id], $buyer->id));

    expect(fn () => booking(fn () => app(SendRfq::class)->handle($rfq)))->toThrow(ProcurementRuleViolated::class, 'email address');

    $mail = pcVendor('With Mail', 'm@x.example');
    $rfq2 = booking(fn (): Rfq => app(CreateRfq::class)->handle(pcApprovedRequisition([['item_id' => $chicken->id, 'quantity' => '4'], ['item_id' => $hilsa->id, 'quantity' => '2']]), [$mail->id, $silent->id], $buyer->id));
    $result = booking(fn (): array => app(SendRfq::class)->handle($rfq2));
    expect($result['skipped'])->toBe(['No Mail Ltd']);

    $rfq2 = booking(fn (): Rfq => Rfq::query()->findOrFail($rfq2->id));
    $partial = booking(fn (): VendorQuotation => app(RecordQuotation::class)->handle($rfq2, $mail->id, [['item_id' => $chicken->id, 'unit_price' => '100']]));

    expect(fn () => booking(fn () => app(AwardRfq::class)->handle($rfq2, $partial, pcStore()->id, $buyer->id)))->toThrow(ProcurementRuleViolated::class, 'leaves out some items');
});

it('orders an approved requisition from one vendor at the vendor\'s price list', function (): void {
    $rice = pcItem('Rice', cost: '100');
    $sugar = pcItem('Sugar', cost: '50');
    $vendor = pcVendor();
    pcPrice($vendor, $rice, '90');
    $requisition = pcApprovedRequisition([['item_id' => $rice->id, 'quantity' => '10'], ['item_id' => $sugar->id, 'quantity' => '4']]);

    $order = booking(fn (): PurchaseOrder => app(CreateOrderFromRequisition::class)->handle($requisition->load('lines'), $vendor->id, pcStore()->id, pcUser(DefaultRole::ProcurementOfficer)->id));
    $prices = $order->lines->mapWithKeys(fn ($line): array => [$line->item_id => (string) $line->unit_price])->all();

    expect($prices)->toBe([$rice->id => '90.0000', $sugar->id => '50.0000'])->and($order->total)->toBe('1100.00')->and($order->payment_terms_days)->toBe(14)
        ->and(booking(fn (): PurchaseRequisitionStatus => PurchaseRequisition::query()->findOrFail($requisition->id)->status))->toBe(PurchaseRequisitionStatus::Ordered)
        ->and(fn () => booking(fn () => app(CreateOrderFromRequisition::class)->handle($requisition->fresh()->load('lines'), $vendor->id, pcStore()->id, 1)))->toThrow(ProcurementRuleViolated::class, 'only an approved');
});

it('prices an order with tax per line and keeps only drafts editable', function (): void {
    $rice = pcItem('Rice');
    $category = booking(fn (): TaxCategory => TaxCategory::query()->firstOrFail());
    $expected = booking(fn (): string => app(TaxEngine::class)->calculate('1000.00', $category->id)->taxTotal);
    $order = pcOrder([['item_id' => $rice->id, 'quantity' => '10', 'unit_price' => '100', 'tax_category_id' => $category->id]]);

    expect($order->subtotal)->toBe('1000.00')->and($order->tax_total)->toBe($expected)->and($order->total)->toBe(number_format(1000 + (float) $expected, 2, '.', ''))->and($order->order_no)->toStartWith('PO-');

    $changed = booking(fn (): PurchaseOrder => app(SavePurchaseOrder::class)->handle($order, new NewPurchaseOrder(bookingIds()['property'], $order->vendor_id, $order->store_id, [['item_id' => $rice->id, 'quantity' => '4', 'unit_price' => '50']], 1)));
    expect($changed->total)->toBe('200.00')->and($changed->lines)->toHaveCount(1);

    booking(fn () => app(SubmitPurchaseOrder::class)->handle($changed, 1));
    expect(fn () => booking(fn () => app(SavePurchaseOrder::class)->handle($changed, new NewPurchaseOrder(bookingIds()['property'], $order->vendor_id, $order->store_id, [['item_id' => $rice->id, 'quantity' => '1', 'unit_price' => '1']], 1))))->toThrow(ProcurementRuleViolated::class, 'only a draft');
});

it('refuses an order with an inactive vendor, a foreign store, repeated items or no price', function (): void {
    $rice = pcItem('Rice');
    $inactive = pcVendor('Closed Ltd', 'c@x.example', 'inactive');
    $line = ['item_id' => $rice->id, 'quantity' => '1', 'unit_price' => '10'];
    $try = fn (array $lines, ?int $vendorId = null, ?int $storeId = null) => booking(fn () => app(SavePurchaseOrder::class)->handle(null, new NewPurchaseOrder(bookingIds()['property'], $vendorId ?? pcVendor()->id, $storeId ?? pcStore()->id, $lines, 1)));

    expect(fn () => $try([$line], $inactive->id))->toThrow(ProcurementRuleViolated::class, 'not active')
        ->and(fn () => $try([$line], null, 999999))->toThrow(ProcurementRuleViolated::class, 'receives the goods')
        ->and(fn () => $try([]))->toThrow(ProcurementRuleViolated::class, 'at least one')
        ->and(fn () => $try([$line, $line]))->toThrow(ProcurementRuleViolated::class, 'once per order')
        ->and(fn () => $try([[...$line, 'unit_price' => '0']]))->toThrow(ProcurementRuleViolated::class, 'above zero');
});

it('approves a small order in one step and a large one in two, then emails the PDF', function (): void {
    $rice = pcItem('Rice');
    $vendor = pcVendor('Dhaka Dry Goods', 'sales@dhakadry.example');
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $accountant = pcUser(DefaultRole::Accountant);
    $gm = pcUser(DefaultRole::GeneralManager);
    $engine = fn () => app(ApprovalEngine::class);
    $status = fn (PurchaseOrder $order): PurchaseOrderStatus => booking(fn (): PurchaseOrderStatus => PurchaseOrder::query()->findOrFail($order->id)->status);

    $small = pcOrder(pcOrderLines([[$rice, '100', '160']]), $vendor);
    expect(fn () => booking(fn () => app(SendPurchaseOrder::class)->handle($small)))->toThrow(ProcurementRuleViolated::class, 'only an approved');
    $small = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle($small, $buyer->id));
    expect($small->status)->toBe(PurchaseOrderStatus::Pending);
    booking(fn () => $engine()->approve((int) $small->approval_request_id, $accountant->id));
    expect($status($small))->toBe(PurchaseOrderStatus::Approved);

    $large = pcOrder(pcOrderLines([[$rice, '500', '160']]), $vendor);
    $large = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle($large, $buyer->id));
    booking(fn () => $engine()->approve((int) $large->approval_request_id, $accountant->id));
    expect($status($large))->toBe(PurchaseOrderStatus::Pending);
    booking(fn () => $engine()->approve((int) $large->approval_request_id, $gm->id));
    expect($status($large))->toBe(PurchaseOrderStatus::Approved);

    $sent = booking(fn (): PurchaseOrder => app(SendPurchaseOrder::class)->handle($large->fresh() ?? $large));
    expect($sent->status)->toBe(PurchaseOrderStatus::Sent)->and($sent->sent_to)->toBe('sales@dhakadry.example')->and($sent->sent_at)->not->toBeNull();

    Notification::assertSentOnDemand(VendorMessage::class, function (VendorMessage $message, array $channels, AnonymousNotifiable $notifiable) use ($large): bool {
        $pdf = $message->attachments[$large->order_no.'.pdf'] ?? '';

        return array_key_exists('sales@dhakadry.example', $notifiable->routes['mail']) && str_contains($message->subject, $large->order_no) && str_starts_with($pdf, '%PDF');
    });
});

it('sends to the primary contact, a given address, and refuses when the vendor has no address', function (): void {
    $rice = pcItem('Rice');
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $approve = function (PurchaseOrder $order) use ($buyer): PurchaseOrder {
        $order = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle($order, $buyer->id));
        booking(fn () => app(ApprovalEngine::class)->approve((int) $order->approval_request_id, pcUser(DefaultRole::Accountant)->id));

        return booking(fn (): PurchaseOrder => PurchaseOrder::query()->findOrFail($order->id));
    };

    $silent = pcVendor('No Mail', null);
    $order = $approve(pcOrder(pcOrderLines([[$rice, '1', '10']]), $silent));
    expect(fn () => booking(fn () => app(SendPurchaseOrder::class)->handle($order)))->toThrow(ProcurementRuleViolated::class, 'no email address');

    $sent = booking(fn (): PurchaseOrder => app(SendPurchaseOrder::class)->handle($order, 'someone@else.example'));
    expect($sent->sent_to)->toBe('someone@else.example');

    $vendor = pcVendor('Has contact', 'main@x.example');
    booking(fn () => app(SaveVendorContact::class)->handle($vendor, null, ['name' => 'Rafiq', 'email' => 'rafiq@x.example', 'is_primary' => true]));
    $second = $approve(pcOrder(pcOrderLines([[$rice, '1', '10']]), $vendor));
    expect(booking(fn (): ?string => app(SendPurchaseOrder::class)->handle($second)->sent_to))->toBe('rafiq@x.example');
});

it('returns a rejected order to a draft and cancels open orders only', function (): void {
    $rice = pcItem('Rice');
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $gm = pcUser(DefaultRole::GeneralManager);
    $order = pcOrder(pcOrderLines([[$rice, '10', '100']]));
    $order = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle($order, $buyer->id));
    booking(fn () => app(ApprovalEngine::class)->reject((int) $order->approval_request_id, $gm->id, 'Too dear'));

    expect(booking(fn (): PurchaseOrderStatus => PurchaseOrder::query()->findOrFail($order->id)->status))->toBe(PurchaseOrderStatus::Draft);

    $cancelled = booking(fn (): PurchaseOrder => app(CancelPurchaseOrder::class)->handle($order, $buyer->id));
    expect($cancelled->status)->toBe(PurchaseOrderStatus::Cancelled)->and($cancelled->cancelled_at)->not->toBeNull()
        ->and(fn () => booking(fn () => app(CancelPurchaseOrder::class)->handle($cancelled, $buyer->id)))->toThrow(ProcurementRuleViolated::class);

    $pending = booking(fn (): PurchaseOrder => app(SubmitPurchaseOrder::class)->handle(pcOrder(pcOrderLines([[$rice, '10', '100']])), $buyer->id));
    booking(fn () => app(CancelPurchaseOrder::class)->handle($pending, $buyer->id));
    expect(booking(fn (): PurchaseOrderStatus => PurchaseOrder::query()->findOrFail($pending->id)->status))->toBe(PurchaseOrderStatus::Cancelled);
});

it('takes a requisition to an emailed order through the screens', function (): void {
    $rice = pcItem('Rice', cost: '100');
    $vendor = pcVendor('Dhaka Dry Goods', 'sales@dhakadry.example');
    $other = pcVendor('Other Dry Goods', 'o@x.example');
    pcPrice($vendor, $rice, '95');
    $store = pcStore();
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/procurement/requisitions'))->assertOk()->assertSeeHtml('data-new-requisition');
    get(tenantUrl('sunrise', '/procurement/requisitions/new'))->assertOk();
    post(tenantUrl('sunrise', '/procurement/requisitions'), ['lines' => []])->assertSessionHasErrors('lines');
    post(tenantUrl('sunrise', '/procurement/requisitions'), ['lines' => [['item_id' => $rice->id, 'quantity' => '20']]])->assertSessionHasNoErrors()->assertSessionHas('success');

    $requisition = booking(fn (): PurchaseRequisition => PurchaseRequisition::query()->sole());
    get(tenantUrl('sunrise', "/procurement/requisitions/{$requisition->id}"))->assertOk()->assertSee('2,000.00')->assertDontSeeHtml('data-create-order');
    booking(fn () => app(ApprovalEngine::class)->approve((int) $requisition->approval_request_id, pcUser(DefaultRole::FnbManager)->id));
    get(tenantUrl('sunrise', "/procurement/requisitions/{$requisition->id}"))->assertOk()->assertSeeHtml('data-create-order')->assertSeeHtml('data-create-rfq');

    post(tenantUrl('sunrise', '/procurement/orders/from-requisition'), ['purchase_requisition_id' => $requisition->id, 'vendor_id' => $vendor->id])->assertSessionHasErrors('store_id');
    post(tenantUrl('sunrise', '/procurement/orders/from-requisition'), ['purchase_requisition_id' => $requisition->id, 'vendor_id' => $vendor->id, 'store_id' => $store->id])->assertSessionHas('success');

    $order = booking(fn (): PurchaseOrder => PurchaseOrder::query()->sole());
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}"))->assertOk()->assertSee('1,900.00')->assertSeeHtml('data-submit-order')->assertDontSeeHtml('data-send-form');
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}/edit"))->assertOk()->assertSeeHtml('data-order-form');
    put(tenantUrl('sunrise', "/procurement/orders/{$order->id}"), ['vendor_id' => $other->id, 'store_id' => $store->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '10', 'unit_price' => '80']]])->assertSessionHas('success');

    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/submit"))->assertSessionHas('success');
    booking(fn () => app(ApprovalEngine::class)->approve((int) $order->fresh()->approval_request_id, pcUser(DefaultRole::Accountant)->id));
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}"))->assertOk()->assertSeeHtml('data-send-form');
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/send"), ['to' => 'bad'])->assertSessionHasErrors('to');
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/send"), [])->assertSessionHas('success');
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}/pdf"))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    get(tenantUrl('sunrise', '/procurement/orders'))->assertOk()->assertSeeHtml('data-order="'.$order->order_no.'"');
    Notification::assertSentOnDemandTimes(VendorMessage::class, 1);
});

it('runs an RFQ through the screens', function (): void {
    $chicken = pcItem('Chicken');
    $farm = pcVendor('Fresh Farm');
    $bay = pcVendor('Bay Fisheries', 'sales@bay.example');
    $store = pcStore();
    $requisition = pcApprovedRequisition([['item_id' => $chicken->id, 'quantity' => '40']]);
    staffUser(DefaultRole::ProcurementOfficer);

    post(tenantUrl('sunrise', '/procurement/rfqs'), ['purchase_requisition_id' => $requisition->id, 'vendors' => [$farm->id]])->assertSessionHasErrors('vendors');
    post(tenantUrl('sunrise', '/procurement/rfqs'), ['purchase_requisition_id' => $requisition->id, 'vendors' => [$farm->id, $bay->id], 'due_on' => '2026-07-05'])->assertSessionHas('success');
    $rfq = booking(fn (): Rfq => Rfq::query()->sole());

    get(tenantUrl('sunrise', '/procurement/rfqs'))->assertOk()->assertSeeHtml('data-rfq="'.$rfq->rfq_no.'"');
    get(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}"))->assertOk()->assertSeeHtml('data-send-rfq')->assertDontSeeHtml('data-quotation-form');
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/send"))->assertSessionHas('success');
    get(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}"))->assertOk()->assertSeeHtml('data-quotation-form');

    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/quotations"), ['vendor_id' => $farm->id, 'prices' => [$chicken->id => '']])->assertSessionHas('error');
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/quotations"), ['vendor_id' => $farm->id, 'prices' => [$chicken->id => '275'], 'reference' => 'FF-1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/quotations"), ['vendor_id' => $bay->id, 'prices' => [$chicken->id => '290'], 'lead_days' => [$chicken->id => '2']])->assertSessionHas('success');

    get(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}"))->assertOk()->assertSeeHtml('data-total="Fresh Farm"')->assertSee('11,000.00')->assertSee('11,600.00')->assertSee('cheapest');

    $winner = booking(fn (): VendorQuotation => VendorQuotation::query()->where('vendor_id', $farm->id)->sole());
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/award"), ['quotation_id' => $winner->id])->assertSessionHasErrors('store_id');
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/award"), ['quotation_id' => $winner->id, 'store_id' => $store->id])->assertSessionHas('success');

    expect(booking(fn (): string => PurchaseOrder::query()->sole()->total))->toBe('11000.00');
    post(tenantUrl('sunrise', "/procurement/rfqs/{$rfq->id}/cancel"))->assertSessionHas('error');
});

it('suggests items to buy through the screens', function (): void {
    $store = pcStore();
    $eggs = pcItem('Eggs', reorder: '100', cost: '14');
    pcStock($store, $eggs, '10', '14');
    staffUser(DefaultRole::StoreKeeper);

    get(tenantUrl('sunrise', '/procurement/suggestions'))->assertOk()->assertSeeHtml('data-suggestion="'.$eggs->sku.'"')->assertSeeHtml('data-create-requisition');
    post(tenantUrl('sunrise', '/procurement/suggestions'), [])->assertSessionHasErrors('items');
    post(tenantUrl('sunrise', '/procurement/suggestions'), ['items' => [$eggs->id], 'quantities' => [$eggs->id => '300']])->assertSessionHas('success');

    expect((string) booking(fn (): string => PurchaseRequisition::query()->sole()->lines->sole()->quantity))->toBe('300.0000');
});

it('keeps purchasing screens to the people who hold the permissions', function (): void {
    $rice = pcItem('Rice');
    $vendor = pcVendor();
    $order = pcOrder(pcOrderLines([[$rice, '1', '10']]), $vendor);
    $requisition = pcRequisition([['item_id' => $rice->id, 'quantity' => '1']]);

    staffUser(DefaultRole::FrontDeskAgent);
    foreach (['/procurement/requisitions', '/procurement/suggestions', '/procurement/rfqs', '/procurement/orders', '/procurement/orders/new', "/procurement/orders/{$order->id}", "/procurement/orders/{$order->id}/pdf", "/procurement/requisitions/{$requisition->id}"] as $url) {
        get(tenantUrl('sunrise', $url))->assertForbidden();
    }
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/submit"))->assertForbidden();
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/send"))->assertForbidden();
    post(tenantUrl('sunrise', '/procurement/rfqs'), [])->assertForbidden();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/procurement/orders'))->assertOk()->assertDontSeeHtml('data-new-order');
    get(tenantUrl('sunrise', "/procurement/orders/{$order->id}"))->assertOk()->assertDontSeeHtml('data-submit-order');
    post(tenantUrl('sunrise', "/procurement/orders/{$order->id}/submit"))->assertForbidden();
    get(tenantUrl('sunrise', '/procurement/orders/new'))->assertForbidden();
    get(tenantUrl('sunrise', '/procurement/requisitions/new'))->assertForbidden();
});
