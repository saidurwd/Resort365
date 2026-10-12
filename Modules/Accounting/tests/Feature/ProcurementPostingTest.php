<?php

/*
| Accounting (Step 5.6), procurement postings. "Done when": a PO with two partial receipts, a bill and a payment flows
| through stock and the ledger; goods received not invoiced is cleared by the bill.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Actions\BackpostHistory;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\PostingService;
use Modules\Accounting\Services\ReportService;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Models\TaxCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Procurement\Actions\RecordVendorPayment;
use Modules\Procurement\Actions\ReturnToVendor;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SaveVendorBill;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\DTOs\NewVendorBill;
use Modules\Procurement\DTOs\NewVendorPayment;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorPaymentMethod;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\VendorBill;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

require_once __DIR__.'/../../../Procurement/tests/Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
    booking(function (): void {
        app(SeedChartOfAccounts::class)->handle();
        app(CreateFiscalYear::class)->handle(CarbonImmutable::parse(Property::query()->where('code', 'CXB')->sole()->business_date->toDateString())->startOfMonth()->subMonths(2)->toDateString());
    });
});

/**
 * The entry's lines as "system key => net debit".
 *
 * @return array<string, string>
 */
function ppEntry(string $type, int $id, string $event): array
{
    return booking(function () use ($type, $id, $event): array {
        $entry = JournalEntry::query()->where('source_type', $type)->where('source_id', $id)->where('source_event', $event)->firstOrFail();
        $lines = [];

        foreach (JournalLine::query()->where('journal_entry_id', $entry->id)->with('account')->get() as $line) {
            $key = $line->account->system_key ?? $line->account->code;
            $lines[$key] = number_format((float) ($lines[$key] ?? 0) + (float) $line->debit - (float) $line->credit, 2, '.', '');
        }

        return $lines;
    });
}

/** Net debit of an account over the whole ledger. */
function ppNet(string $systemKey): string
{
    return booking(function () use ($systemKey): string {
        $id = Account::query()->where('system_key', $systemKey)->value('id');

        return number_format((float) JournalLine::query()->where('account_id', $id)->sum('debit') - (float) JournalLine::query()->where('account_id', $id)->sum('credit'), 2, '.', '');
    });
}

it('flows a purchase from two receipts through the bill and a payment to the ledger', function (): void {
    $rice = pcItem('Rice');
    $sugar = pcItem('Sugar');
    $vendor = pcVendor('Dhaka Dry Goods');
    $order = pcApprovedOrder([[$rice, '100', '160'], [$sugar, '50', '118']], $vendor);

    $first = pcReceive($order, [0 => '60', 1 => '50']);
    $second = pcReceive(pcFresh($order), [0 => '40']);
    expect(ppEntry('goods_receipt', $first->id, 'received'))->toBe(['inventory_food' => '15500.00', 'grni' => '-15500.00'])
        ->and(ppEntry('goods_receipt', $second->id, 'received'))->toBe(['inventory_food' => '6400.00', 'grni' => '-6400.00'])
        ->and(ppNet('grni'))->toBe('-21900.00');

    $bill = pcApproveBill(pcBill(pcFresh($order), [0 => ['100', '160'], 1 => ['50', '118']]));
    expect(ppEntry('vendor_bill', $bill->id, 'approved'))->toBe(['grni' => '21900.00', 'accounts_payable' => '-21900.00'])
        ->and(ppNet('grni'))->toBe('0.00')->and(ppNet('accounts_payable'))->toBe('-21900.00')->and(ppNet('inventory_food'))->toBe('21900.00');

    $payment = booking(fn () => app(RecordVendorPayment::class)->handle(new NewVendorPayment(bookingIds()['property'], $vendor->id, VendorPaymentMethod::BankTransfer, '20000', pcUser(DefaultRole::Accountant)->id, [$bill->id => '20000'])));
    expect(ppEntry('vendor_payment', $payment->id, 'paid'))->toBe(['accounts_payable' => '20000.00', 'bank' => '-20000.00'])->and(ppNet('accounts_payable'))->toBe('-1900.00');

    // Idempotent: the same event posts once.
    booking(fn () => app(PostingService::class)->vendorBill($bill->id));
    expect(booking(fn (): int => JournalEntry::query()->where('source_type', 'vendor_bill')->count()))->toBe(1);
});

it('puts the vendor on the payable and shows it in the payables aging', function (): void {
    $rice = pcItem('Rice');
    $vendor = pcVendor('Dhaka Dry Goods');
    $order = pcApprovedOrder([[$rice, '10', '100']], $vendor);
    pcReceive($order, [0 => '10']);
    pcApproveBill(pcBill(pcFresh($order), [0 => ['10', '100']]));

    $parties = booking(fn (): array => JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'accounts_payable'))->get()->map(fn (JournalLine $line): string => $line->party_type?->value.':'.$line->party_id)->all());
    $report = booking(fn () => app(ReportService::class)->aging(false, now()->addDay()->toDateString()));

    expect($parties)->toBe(['vendor:'.$vendor->id])->and(collect($report->rows)->pluck('cells.0')->all())->toContain('Dhaka Dry Goods');
});

it('books a billed price above the order price as purchase price variance', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    pcReceive($order, [0 => '100']);
    $bill = pcApproveBill(pcBill(pcFresh($order), [0 => ['100', '163']], override: true));

    expect(ppEntry('vendor_bill', $bill->id, 'approved'))->toBe(['grni' => '16000.00', 'purchase_price_variance' => '300.00', 'accounts_payable' => '-16300.00'])
        ->and(ppNet('grni'))->toBe('0.00');
});

it('recovers the input VAT on a goods bill', function (): void {
    $rice = pcItem('Rice');
    $category = booking(fn (): TaxCategory => TaxCategory::query()->firstOrFail());
    $vendor = pcVendor();
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $order = booking(fn () => app(SavePurchaseOrder::class)->handle(null, new NewPurchaseOrder(bookingIds()['property'], $vendor->id, pcStore()->id, [['item_id' => $rice->id, 'quantity' => '10', 'unit_price' => '100', 'tax_category_id' => $category->id]], $buyer->id)));
    $order = booking(fn () => app(SubmitPurchaseOrder::class)->handle($order, $buyer->id));
    booking(fn () => app(ApprovalEngine::class)->approve((int) $order->approval_request_id, pcUser(DefaultRole::Accountant)->id));
    $order = booking(fn () => PurchaseOrder::query()->with('lines')->findOrFail($order->id));
    pcReceive($order, [0 => '10']);
    $bill = pcApproveBill(pcBill($order, [0 => ['10', '100']]));

    expect($bill->tax_total)->not->toBe('0.00')
        ->and(ppEntry('vendor_bill', $bill->id, 'approved'))->toBe(['grni' => '1000.00', 'input_vat' => $bill->tax_total, 'accounts_payable' => '-'.$bill->total]);
});

it('books a service bill to its expense with the department', function (): void {
    $vendor = pcVendor();
    $department = booking(fn (): Department => Department::factory()->create());
    $buyer = pcUser(DefaultRole::ProcurementOfficer);
    $bill = booking(fn (): VendorBill => app(SaveVendorBill::class)->handle(null, new NewVendorBill(bookingIds()['property'], $vendor->id, VendorBillKind::Service, [['description' => 'Pump service', 'expense_class' => 'repairs', 'quantity' => '1', 'unit_price' => '12000']], $buyer->id, departmentId: $department->id)));
    $bill = pcApproveBill($bill);

    expect(ppEntry('vendor_bill', $bill->id, 'approved'))->toBe(['repairs_expense' => '12000.00', 'accounts_payable' => '-12000.00'])
        ->and(booking(fn () => JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'repairs_expense'))->value('department_id')))->toBe($department->id);
});

it('reverses GRNI for unbilled goods returned and reduces the payable for billed ones', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    $receipt = pcReceive($order, [0 => '100']);
    $lineId = $receipt->lines[0]->id;
    $unbilled = booking(fn () => app(ReturnToVendor::class)->handle($receipt, [['goods_receipt_line_id' => $lineId, 'quantity' => '10']], 'Damaged', 1));

    expect(ppEntry('purchase_return', $unbilled->id, 'returned'))->toBe(['grni' => '1600.00', 'inventory_food' => '-1600.00']);

    pcApproveBill(pcBill(pcFresh($order), [0 => ['90', '160']]));
    $billed = booking(fn () => app(ReturnToVendor::class)->handle(pcFreshReceipt($receipt), [['goods_receipt_line_id' => $lineId, 'quantity' => '20']], 'Wrong grade', 1));

    expect(ppEntry('purchase_return', $billed->id, 'returned'))->toBe(['accounts_payable' => '3200.00', 'inventory_food' => '-3200.00'])
        ->and(ppNet('grni'))->toBe('0.00')->and(ppNet('accounts_payable'))->toBe('-11200.00');
});

it('keeps inventory in the ledger equal to the stock ledger through receipts and returns', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '100', '160']]);
    $receipt = pcReceive($order, [0 => '100']);
    booking(fn () => app(ReturnToVendor::class)->handle($receipt, [['goods_receipt_line_id' => $receipt->lines[0]->id, 'quantity' => '25']], 'Damaged', 1));
    $stock = booking(fn (): string => (string) StockBalance::query()->where('item_id', $rice->id)->selectRaw('sum(quantity * avg_cost) as value')->value('value'));

    expect(ppNet('inventory_food'))->toBe(number_format((float) $stock, 2, '.', ''));
});

it('back-posts procurement history and reports an unmapped account instead of failing', function (): void {
    $rice = pcItem('Rice');
    $order = pcApprovedOrder([[$rice, '10', '100']]);
    booking(fn () => Account::query()->where('system_key', 'grni')->update(['system_key' => null]));

    $receipt = pcReceive($order, [0 => '10']);
    expect($receipt->total_value)->toBe('1000.00')->and(booking(fn (): int => JournalEntry::query()->where('source_type', 'goods_receipt')->count()))->toBe(0);

    booking(fn () => Account::query()->where('code', '2220')->update(['system_key' => 'grni']));
    $result = booking(fn (): array => app(BackpostHistory::class)->handle());

    expect($result['failed'])->toBe([])->and(booking(fn (): int => JournalEntry::query()->where('source_type', 'goods_receipt')->count()))->toBe(1);
});
