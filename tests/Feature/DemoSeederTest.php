<?php

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\PropertyAccess;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\ReportService;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\Payment;
use Modules\Core\Contracts\PlanLimits;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Models\TaxCategory;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;
use Modules\HR\Enums\AttendanceSource;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Enums\HistoryEvent;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\AttendanceImport;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeHistory;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;
use Modules\HR\Services\AttendanceReports;
use Modules\IAM\Models\User;
use Modules\Inventory\Enums\StockClass;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Models\EmployeeSalary;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Models\SalaryStructure;
use Modules\Payroll\Models\ServiceChargeAllocation;
use Modules\Payroll\Models\ServiceChargePool;
use Modules\Payroll\Models\TaxTable;
use Modules\Payroll\Services\SalaryPreview;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorItem;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Models\VendorQuotation;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;
use Modules\Property\Services\OccupancyCalculator;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Services\RateCalendar;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\Reservation;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\DeliveryStatus;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\DiscountLimit;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Models\SaleConsumption;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Services\Reports\FnbTrends;
use Modules\Restaurant\Services\Reports\MenuEngineeringReport;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('produces the full demo: tenants, properties, one user per role and property access', function (): void {
    seed(DatabaseSeeder::class);

    expect(Tenant::query()->orderBy('slug')->pluck('slug')->all())->toBe(['greenvalley', 'rodela'])
        ->and(tenant('rodela')->name)->toBe('Rodela Eco Resort');

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(Property::query()->pluck('name', 'code')->all())->toBe(['CXB' => 'Rodela Eco Resort'])
            ->and(User::query()->count())->toBe(18);

        $access = fn (string $email): array => array_values(app(PropertyAccess::class)->accessibleProperties(User::query()->where('email', $email)->firstOrFail()));

        expect($access('frontdesk@rodelaresort.com'))->toBe(['Rodela Eco Resort'])
            ->and($access('owner@rodelaresort.com'))->toBe(['Rodela Eco Resort']);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(Property::query()->pluck('name')->all())->toBe(['Green Valley'])
            ->and(User::query()->count())->toBe(18);
    });
});

it('sets up each resort with single-room and multi-room cottages (Step 1.1)', function (): void {
    seed(DatabaseSeeder::class);

    $layout = fn (string $code): array => Cottage::query()->with(['rooms.roomType'])
        ->whereHas('property', fn ($query) => $query->where('code', $code))->orderBy('sort_order')->get()
        ->map(fn (Cottage $cottage): int => $cottage->rooms->count())->all();

    app(TenantContext::class)->run(tenant('rodela'), function () use ($layout): void {
        expect($layout('CXB'))->toBe([1, 1, 1, 2, 2, 2, 3, 3])
            ->and(Amenity::query()->count())->toBe(15)
            ->and(Department::query()->count())->toBe(11);

        $villa = Cottage::query()->with('rooms.roomType')->where('code', 'C07')->sole();
        expect($villa->booking_mode)->toBe(BookingMode::WholeOnly)
            ->and($villa->rooms->pluck('roomType.code')->all())->toBe(['FS', 'DK', 'TW'])
            ->and(app(OccupancyCalculator::class)->forCottage($villa))->toBe(11);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function () use ($layout): void {
        expect($layout('GVR'))->toBe([1, 1, 1, 2, 4]);
    });
});

it('seeds guests to search at scale, with a duplicate pair and a blacklisted guest (Step 1.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        // 10,000 seeded, plus three guests named on the demo group's rooming list (Step 2.4).
        expect(Guest::query()->count())->toBe(10_003)
            ->and(Company::query()->count())->toBe(8)
            ->and(TravelAgent::query()->count())->toBe(5)
            ->and(Guest::query()->where('phone', '+8801711000001')->count())->toBe(2)
            ->and(Guest::query()->where('is_blacklisted', true)->pluck('first_name')->all())->toBe(['Kamal'])
            ->and(array_map(fn (GuestSummary $guest) => $guest->name, app(GuestLookup::class)->search('01711-000001')))->toEqualCanonicalizing(['Rahim Uddin', 'Mr Rahim Uddin']);

        // Bulk-inserted guests are readable like any other: decrypted ID, hash in step.
        $guest = Guest::query()->where('phone', '+8801710000001')->sole();
        expect($guest->id_number)->toBe('1000000001')
            ->and(app(GuestLookup::class)->search('1000000001')[0]->id)->toBe($guest->id);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(Guest::query()->count())->toBe(200));
});

it('seeds taxes, seasons, rate plans and rates (Step 1.3)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $room = TaxCategory::query()->where('code', 'ROOM')->sole();
        $cxb = Property::query()->where('code', 'CXB')->sole();
        $plan = RatePlan::query()->where('property_id', $cxb->id)->where('code', 'RO')->sole();
        $units = app(InventoryCatalog::class)->unitTypes($cxb->id);
        $year = CarbonImmutable::now()->year;
        $days = fn (string $from, string $to): array => app(RateCalendar::class)->rates($plan, array_map(fn (UnitTypeSummary $unit) => $unit->key(), $units), CarbonImmutable::parse($from), CarbonImmutable::parse($to));

        expect(app(TaxEngine::class)->calculate('1000', $room->id)->gross)->toBe('1265.00')
            ->and(RatePlan::query()->where('property_id', $cxb->id)->pluck('code')->sort()->values()->all())->toBe(['BB', 'HB', 'NRF', 'RO'])
            ->and(DepositPolicy::query()->where('property_id', $cxb->id)->where('is_default', true)->value('name'))->toBe('Standard advance')
            ->and(CancellationPolicy::query()->where('property_id', $cxb->id)->pluck('name')->sort()->values()->all())->toBe(['Flexible', 'Non-refundable'])
            ->and(RatePlan::query()->where('code', 'NRF')->sole()->cancellationPolicy?->name)->toBe('Non-refundable')
            ->and(Promotion::query()->where('property_id', $cxb->id)->pluck('code')->filter()->sort()->values()->all())->toBe(['EARLYBIRD', 'MONSOON20']);

        $villaKey = collect($units)->firstWhere('code', 'FV')?->key();
        $kingKey = collect($units)->firstWhere('code', 'DK')?->key();
        $newYear = $days("{$year}-12-30", "{$year}-12-31");

        // Deluxe King: 6,000 base; Peak ×1.4 = 8,400; New Year's Eve date price 6,000 × 1.8 = 10,800.
        expect($newYear[$kingKey]["{$year}-12-31"]?->amount)->toBe('10800.00')
            ->and($newYear[$kingKey]["{$year}-12-30"]?->seasonName)->toBe('Peak')
            ->and($newYear[$villaKey]["{$year}-12-31"]?->amount)->toBe('45000.00');
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(RatePlan::query()->pluck('code')->sort()->values()->all())->toBe(['BB', 'RO']));
});

it('seeds bookings that lock their rooms: tentative, confirmed by a deposit, and an overdue hold (Steps 1.6–1.8)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        // The year of past bookings the reports use (codes RSV-H…, Step 7.1) is history, with no locks or folios.
        $bookings = Reservation::query()->with('items')->where('code', 'not like', 'RSV-H%')->orderBy('check_in')->orderBy('id')->get();

        // In house in 402 (2 nights, leaving today), arriving today in 201 (2 nights) and 703 (the night audit's no-show,
        // 1 night), room 601 (overdue hold, 1 night), room 501 (2 nights), room 101 (3 nights), Sunset Villa (3 rooms × 3 nights).
        $group = Reservation::query()->whereNotNull('group_name')->sole();
        expect([$group->group_name, $group->items()->count(), $group->status->value])->toBe(['Dhaka Bank offsite', 6, 'confirmed'])
            ->and(Folio::query()->where('reservation_id', $group->id)->where('type', 'master')->exists())->toBeTrue();
        $bookings = $bookings->reject(fn (Reservation $reservation): bool => $reservation->id === $group->id)->values();

        expect($bookings->pluck('status')->map->value->all())->toBe(['checked_in', 'confirmed', 'confirmed', 'tentative', 'tentative', 'confirmed', 'tentative', 'cancelled'])
            ->and($bookings[2]->items->sole()->check_out->diffInDays($bookings[2]->check_in, true))->toEqual(1)
            ->and($bookings[3]->deposit_due_at?->isPast())->toBeTrue()
            ->and($bookings[4]->deposit_percent)->toBe('50.00')
            ->and($bookings[5]->amount_paid)->toBe($bookings[5]->deposit_required)
            ->and(Payment::query()->pluck('receipt_no')->all())->toHaveCount(6)
            ->and(CashierShift::query()->where('status', 'open')->count())->toBe(1)
            ->and(InventoryLock::query()->where('lock_type', 'reservation')->count())->toBe(2 + 2 + 1 + 1 + 2 + 3 + 3 * 3 + 6 * 2)
            ->and(ExtraService::query()->count())->toBe(6)
            ->and(CityLedgerEntry::query()->sole()->due_on->isPast())->toBeTrue()
            ->and(Folio::query()->count())->toBe(10)
            ->and(FolioLine::query()->where('line_type', 'charge')->pluck('description')->all())->toBe(['Airport pickup on arrival (11:00)', 'Main Restaurant bill MR-B000002', 'Room Service bill RS-B000001'])
            ->and(Quote::query()->orderBy('check_in')->get()->map(fn (Quote $quote): string => $quote->currentStatus()->value)->all())->toBe(['sent', 'expired']);
    });

    ExpireTentativeHolds::dispatchSync();
    app(TenantContext::class)->run(tenant('rodela'), fn () => expect(Reservation::query()->where('code', 'not like', 'RSV-H%')->orderBy('check_in')->orderBy('id')->pluck('status')->map->value->all())
        ->toBe(['checked_in', 'confirmed', 'confirmed', 'confirmed', 'cancelled', 'tentative', 'confirmed', 'tentative', 'cancelled']));
});

it('sets up the restaurant: outlets with stations, terminals and floor plans (Step 3.1)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $outlets = [];

        foreach (Outlet::query()->withCount(['areas', 'tables', 'stations', 'terminals'])->orderBy('sort_order')->orderBy('name')->get() as $outlet) {
            $outlets[$outlet->name] = [(int) $outlet->getAttribute('areas_count'), (int) $outlet->getAttribute('tables_count'),
                (int) $outlet->getAttribute('stations_count'), (int) $outlet->getAttribute('terminals_count')];
        }

        expect($outlets)->toBe(['Main Restaurant' => [2, 12, 4, 2], 'Pool Bar' => [1, 4, 1, 1], 'Room Service' => [0, 0, 1, 1]])
            ->and(DiningTable::query()->where('number', 'T8')->sole()->only(['pos_x', 'pos_y']))->toBe(['pos_x' => 600, 'pos_y' => 200]);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(DiningTable::query()->count())->toBe(6));
});

it('seeds a menu of about 60 items priced differently at the outlets (Step 3.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $outlets = Outlet::query()->pluck('id', 'code');
        $price = fn (string $code, string $outlet, ?string $variant = null): ?string => OutletMenuItem::query()->where('outlet_id', $outlets[$outlet])
            ->where('menu_item_id', MenuItem::query()->where('code', $code)->value('id'))
            ->where('variant_key', $variant !== null ? MenuItemVariant::query()->where('name', $variant)->whereHas('item', fn ($query) => $query->where('code', $code))->value('id') : 0)
            ->value('price');

        expect(MenuItem::query()->count())->toBe(63)
            ->and(MenuItemVariant::query()->count())->toBeGreaterThan(20)
            ->and(ModifierGroup::query()->count())->toBe(4)
            ->and([$price('FJ04', 'MR'), $price('FJ04', 'PB'), $price('FJ04', 'RS')])->toBe(['180.00', '198.00', '207.00'])
            ->and([$price('BD01', 'MR', 'Full'), $price('BD01', 'RS', 'Full'), $price('BD01', 'PB', 'Full')])->toBe(['650.00', '748.00', null])
            ->and(OutletMenuItem::query()->where('outlet_id', $outlets['MR'])->where('is_available', false)->count())->toBe(2);
    });
});

it('seeds an open order at table T4 with mains and drinks sent and desserts held (Step 3.4)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $order = PosOrder::query()->with(['lines', 'kots.station', 'table'])->whereHas('table', fn ($query) => $query->where('number', 'T4'))->sole();

        expect($order->table?->number)->toBe('T4')
            ->and($order->covers)->toBe(2)
            ->and($order->order_no)->toBe('MR-0001')
            ->and($order->lines->where('status', OrderLineStatus::Sent))->toHaveCount(4)
            ->and($order->lines->where('is_held', true)->pluck('name_snapshot')->all())->toBe(['Rasmalai'])
            ->and($order->kots->map(fn (Kot $kot): ?string => $kot->station?->name)->all())->toBe(['Hot kitchen', 'Bar'])
            ->and($order->table?->status)->toBe(TableStatus::Occupied)
            ->and($order->subtotal)->toBe('2900.00')
            // Step 3.5: the Hot kitchen's screen registers with the demo display token.
            ->and(KitchenStation::query()->where('display_token', hash('sha256', DemoRestaurant::DEMO_DISPLAY_TOKEN))->sole()->name)->toBe('Hot kitchen')
            // Step 3.6: T6's lunch settled by card (with a tip) in the cashier's open session; discount limits per role.
            ->and(PosBill::query()->orderBy('id')->first()?->only(['bill_no', 'status', 'grand_total', 'tip_total']))->toBe(['bill_no' => 'MR-B000001', 'status' => BillStatus::Settled, 'grand_total' => '974.05', 'tip_total' => '50.00'])
            ->and(DiscountLimit::query()->count())->toBe(3)
            // Step 3.7: room 402's breakfast for two on the meal plan, the coffees on their folio.
            ->and(PackageRedemption::query()->orderBy('id')->first()?->only(['covers_adults', 'entitled']))->toBe(['covers_adults' => 2, 'entitled' => 2])
            ->and(FolioLine::query()->where('reference_type', 'pos_bill')->orderBy('id')->first()?->total)->toBe('556.60')
            // Step 3.8: room service delivered to 402 and charged to the room; a pool delivery on its way; a staff meal;
            // three table reservations; yesterday's Pool Bar sales, with a voided item.
            ->and(PosOrder::query()->where('order_type', 'room_service')->sole()->only(['delivery_status', 'delivery_location']))->toBe(['delivery_status' => DeliveryStatus::Delivered, 'delivery_location' => 'Room 402'])
            ->and(PosOrder::query()->where('order_type', 'location_delivery')->sole()->delivery_location)->toBe('Pool deck, bed 4')
            ->and(PosOrder::query()->where('order_type', 'staff_meal')->sole()->guest_name)->toBe('Kitchen team')
            ->and(TableReservation::query()->count())->toBe(3)
            ->and(PosBill::query()->whereHas('outlet', fn ($query) => $query->where('code', 'PB'))->whereDoesntHave('order', fn ($query) => $query->where('order_no', 'like', 'H%'))->where('status', 'settled')->count())->toBe(3)
            ->and(PosOrderLine::query()->where('status', 'voided')->count())->toBe(1);
    });
});

it('seeds the inventory: units, stores and about 150 items, outlets linked to their stores (Step 5.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $stores = Store::query()->orderBy('code')->pluck('kind', 'code')->map(fn ($kind): string => $kind->value)->all();

        expect($stores)->toBe(['BAR' => 'bar', 'HK' => 'housekeeping', 'KITCHEN' => 'kitchen', 'MAIN' => 'main', 'MNT' => 'maintenance'])
            ->and(Item::query()->count())->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(170)
            ->and(Unit::query()->where('code', 'kg')->sole()->factor)->toBe('1000.000000')
            ->and(Outlet::query()->orderBy('code')->get()->map(fn (Outlet $outlet): string => $outlet->code.'='.Store::query()->find($outlet->store_id)?->code)->all())->toBe(['MR=KITCHEN', 'PB=BAR', 'RS=KITCHEN'])
            ->and(Item::query()->where('name', 'Basmati rice')->sole()->conversions()->sole()->factor)->toBe('25.000000');
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(Store::query()->count())->toBe(2));
});

it('seeds stock: opening balances, a dearer delivery, one transfer received and one in transit, damage and expired eggs (Step 5.3)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $balance = fn (string $store, string $item): StockBalance => StockBalance::query()->whereHas('store', fn ($query) => $query->where('code', $store))->whereHas('item', fn ($query) => $query->where('name', $item))->sole();

        expect(StockBalance::query()->count())->toBeGreaterThan(150)
            ->and($balance('KITCHEN', 'Basmati rice')->quantity)->toBe('560.0000')->and($balance('KITCHEN', 'Basmati rice')->avg_cost)->toBe('167.3214')
            ->and($balance('MAIN', 'Basmati rice')->quantity)->toBe('540.0000')
            ->and(StockTransfer::query()->orderBy('id')->get()->map(fn (StockTransfer $transfer): string => $transfer->status->value)->all())->toBe(['received', 'dispatched'])
            ->and($balance('MAIN', 'Wheat flour')->quantity)->toBe('270.0000')->and($balance('KITCHEN', 'Eggs')->quantity)->toBe('196.6400')->and($balance('BAR', 'Mineral water 500 ml')->quantity)->toBe('18.0000')
            ->and(StockMovement::query()->where('movement_type', 'opening')->count())->toBeGreaterThan(150);
    });
});

it('seeds requisitions, issues and counts, posted to the ledger (Step 5.4)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(StockRequisition::query()->orderBy('id')->get()->map(fn (StockRequisition $requisition): string => $requisition->status->value)->all())->toBe(['issued', 'pending'])
            ->and(StockIssue::query()->orderBy('id')->pluck('reason')->all())->toBe(['requisition', 'Room amenities'])
            ->and(StockCount::query()->orderBy('id')->get()->map(fn (StockCount $count): string => $count->status->value)->all())->toBe(['completed', 'counting'])
            ->and(ItemCategory::query()->whereNull('parent_id')->where('name', 'Food')->value('account_class'))->toBe(StockClass::Food)
            ->and(JournalEntry::query()->whereIn('source_type', ['stock_issue', 'stock_count'])->count())->toBe(3);
    });
});

it('seeds vendors, requisitions, an awarded RFQ and purchase orders (Step 5.5)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(Vendor::query()->count())->toBe(5)->and(VendorItem::query()->count())->toBeGreaterThan(20)
            ->and(PurchaseRequisition::query()->orderBy('id')->get()->map(fn (PurchaseRequisition $requisition): string => $requisition->source->value.':'.$requisition->status->value)->all())->toBe(['reorder:approved', 'manual:ordered'])
            ->and(Rfq::query()->sole()->status->value)->toBe('awarded')->and(VendorQuotation::query()->count())->toBe(2)
            ->and(PurchaseOrder::query()->orderBy('id')->get()->map(fn (PurchaseOrder $order): string => $order->status->value.':'.$order->total)->all())->toBe(['closed:63200.00', 'pending:29300.00'])
            ->and(GoodsReceipt::query()->count())->toBe(2)->and(VendorBill::query()->orderBy('id')->get()->map(fn (VendorBill $bill): string => $bill->status->value)->all())->toBe(['partial', 'pending'])
            ->and(VendorPayment::query()->count())->toBe(2)->and(PurchaseReturn::query()->count())->toBe(1)
            ->and(JournalEntry::query()->whereIn('source_type', ['goods_receipt', 'vendor_bill', 'vendor_payment', 'purchase_return'])->count())->toBe(6);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), fn () => expect(Vendor::query()->count())->toBe(0));
});

it('seeds recipes, the stock the demo sales used, and wastage (Step 5.7)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $burger = MenuItem::query()->where('code', 'GR04')->sole();
        $sauce = Recipe::query()->where('kind', 'sub')->sole();

        expect(Recipe::query()->count())->toBe(20)->and($sauce->name)->toBe('Burger sauce')->and(Recipe::query()->where('menu_item_id', $burger->id)->sole()->lines)->toHaveCount(6)
            ->and(SaleConsumption::query()->count())->toBeGreaterThan(10)->and(MenuItem::query()->where('code', 'SD02')->value('inventory_item_id'))->not->toBeNull()
            ->and(WastageEntry::query()->sole()->total_cost)->toBe('643.25')
            ->and(JournalEntry::query()->whereIn('source_event', ['consumed', 'wasted'])->count())->toBeGreaterThan(5);
    });
});

it('seeds stock that matches the ledger, with opening balances posted (Step 5.8)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $report = app(ReportService::class)->inventoryReconciliation(new ReportFilter('2000-01-01', now()->addDay()->toDateString()));
        $total = collect($report->rows)->last()['cells'];

        expect(JournalEntry::query()->where('source_type', 'stock_opening')->count())->toBeGreaterThan(0)
            ->and(abs((float) str_replace(',', '', $total[4])))->toBeLessThan(0.1)->and((float) str_replace(',', '', $total[1]))->toBeGreaterThan(0.0);
    });
});

it('seeds about 60 employees in a reporting tree with a little history (Step 6.1)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $statuses = Employee::query()->get()->countBy(fn (Employee $employee): string => $employee->status->value);
        $general = Employee::query()->whereNull('manager_id')->get();

        expect(Employee::query()->count())->toBeBetween(55, 70)->and($statuses['exited'])->toBe(1)->and($statuses['on_probation'])->toBe(1)
            ->and(Employee::query()->distinct()->count('department_id'))->toBe(11)->and($general)->toHaveCount(1)->and($general->first()->designation->name)->toBe('General Manager')
            ->and(Employee::query()->whereNotNull('user_id')->count())->toBe(16)->and(Employee::query()->where('user_id', User::query()->where('email', 'hr@rodelaresort.com')->value('id'))->sole()->designation->name)->toBe('HR Manager')
            ->and(EmployeeHistory::query()->pluck('event')->unique()->map(fn (HistoryEvent $event): string => $event->value)->sort()->values()->all())->toBe(['confirmation', 'disciplinary', 'exit', 'increment', 'joined', 'promotion', 'transfer'])
            ->and(Employee::query()->whereNotNull('national_id_hash')->count())->toBe(Employee::query()->count());
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(Employee::query()->count())->toBeBetween(5, 12)->and(Designation::query()->count())->toBeGreaterThan(3);
    });
});

it('seeds shifts, rosters and a month of imported attendance that shows lateness and overtime (Step 6.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $lastMonth = now()->subMonthNoOverflow()->format('Y-m');
        $property = Property::query()->where('code', 'CXB')->firstOrFail();
        $report = app(AttendanceReports::class)->monthly($property->id, $lastMonth, null, now()->toDateString());
        $imported = Attendance::query()->where('source', AttendanceSource::Import->value)->count();

        expect(Shift::query()->pluck('code')->sort()->values()->all())->toBe(['EVE', 'GEN', 'MOR', 'NGT'])->and(Holiday::query()->count())->toBe(7)->and(Roster::query()->count())->toBeGreaterThan(3000)
            ->and($imported)->toBeGreaterThan(1000)->and(Attendance::query()->where('source', AttendanceSource::Manual->value)->count())->toBe(3)
            ->and((int) $report['totals'][6])->toBeGreaterThan(20)->and($report['totals'][9])->not->toBe('0:00')->and(Attendance::query()->where('status', AttendanceStatus::Incomplete->value)->exists())->toBeTrue()
            ->and(Attendance::query()->where('status', AttendanceStatus::Holiday->value)->exists())->toBeTrue()->and(AttendanceImport::query()->count())->toBe(1);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(Shift::query()->count())->toBe(4)->and(Roster::query()->count())->toBeGreaterThan(100);
    });
});

it('seeds the Bangladesh payroll starter pack and a salary for every employee (Step 6.4)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $today = now()->toDateString();
        $working = Employee::query()->where('status', '!=', 'exited')->pluck('id');
        $general = Employee::query()->whereHas('designation', fn ($query) => $query->where('name', 'General Manager'))->firstOrFail();
        $pay = app(SalaryPreview::class)->forEmployee($general->id, $today);

        expect(SalaryComponent::query()->count())->toBe(15)->and(SalaryStructure::query()->count())->toBe(2)->and(TaxTable::query()->count())->toBe(1)
            ->and(EmployeeSalary::query()->whereNull('effective_to')->pluck('employee_id')->unique()->sort()->values()->all())->toBe($working->sort()->values()->all())
            ->and(EmployeeSalary::query()->whereNotNull('effective_to')->count())->toBeGreaterThan(5)
            // 150,000 + 75,000 (50%) + 15,000 (10%) + 5,000 conveyance set for the General Manager.
            ->and($pay?->gross)->toBe('245000.00')->and((float) $pay?->tax)->toBeGreaterThan(0.0)->and((float) $pay?->net)->toBeLessThan(245000.0);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(EmployeeSalary::query()->distinct()->count('employee_id'))->toBe(Employee::query()->count());
    });
});

it('seeds last month\'s payroll through approval, payment and the ledger, two staff loans and this month\'s draft (Step 6.5)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $last = now()->subMonthNoOverflow()->startOfMonth();
        $run = PayrollRun::query()->whereDate('period', $last->toDateString())->firstOrFail();
        $entries = JournalEntry::query()->where('source_type', 'payroll_run')->where('source_id', $run->id)->pluck('source_event')->sort()->values()->all();
        $payable = JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'salaries_payable'))->selectRaw('SUM(credit) - SUM(debit) as owed')->value('owed');

        expect($run->status)->toBe(RunStatus::Paid)->and($run->employees_count)->toBe(Employee::query()->where('status', '!=', 'exited')->count())->and((float) $run->net_total)->toBeGreaterThan(0.0)
            ->and(Payslip::query()->where('payroll_run_id', $run->id)->count())->toBe($run->employees_count)
            ->and($entries)->toBe(['approved', 'paid'])->and((float) $payable)->toBe(0.0)
            ->and(Loan::query()->count())->toBe(2)->and(PayrollRun::query()->whereDate('period', $last->addMonth()->toDateString())->firstOrFail()->status)->toBe(RunStatus::Calculated);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(PayrollRun::query()->count())->toBe(2);
    });
});

it('seeds the month\'s service charge and tips pools, sent to payroll and paid out through the approved run (Step 6.6)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $thisMonth = PayrollRun::query()->whereDate('period', now()->startOfMonth()->toDateString())->firstOrFail();
        $pools = ServiceChargePool::query()->get();
        $finalised = ServiceChargePool::query()->where('status', 'finalised')->whereDate('period', now()->startOfMonth()->toDateString())->pluck('id');
        $shared = (float) PayslipLine::query()->whereIn('code', ['SCSHARE', 'TIPSHARE'])->whereIn('payslip_id', Payslip::query()->where('payroll_run_id', $thisMonth->id)->pluck('id'))->sum('amount');
        $paidOut = (float) JournalLine::query()->whereHas('account', fn ($query) => $query->where('system_key', 'service_charge_payable'))->whereHas('entry', fn ($query) => $query->where('source_type', 'payroll_run'))->sum('debit');
        $distributed = (float) ServiceChargeAllocation::query()->whereHas('pool', fn ($query) => $query->where('status', 'distributed')->where('kind', 'service_charge'))->sum('amount');

        expect($pools)->toHaveCount(4)->and((float) $pools->sum('collected'))->toBeGreaterThan(0.0)
            ->and($shared)->toBe((float) ServiceChargeAllocation::query()->whereIn('service_charge_pool_id', $finalised)->sum('amount'))
            ->and($paidOut)->toBe($distributed);
    });
});

it('seeds twelve weeks of restaurant history that puts dishes in every menu engineering class, and a budget for the year (Step 7.2)', function (): void {
    seed(DatabaseSeeder::class);

    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        $outlet = Outlet::query()->where('code', 'MR')->firstOrFail();
        $property = Property::query()->firstOrFail();
        $to = $property->business_date->subDay()->toDateString();
        $from = $property->business_date->subDays(84)->toDateString();
        $built = app(MenuEngineeringReport::class)->build($property->id, $from, $to, [$outlet->id], null);
        $classes = collect($built['table']['rows'])->pluck(8)->unique()->sort()->values()->all();
        $trends = app(FnbTrends::class)->build($property->id, $from, $to, [$outlet->id], 'week');
        $budget = Budget::query()->with('lines')->sole();

        expect($classes)->toBe(['Dog', 'Plowhorse', 'Puzzle', 'Star'])->and(count($built['table']['rows']))->toBeGreaterThan(10)->and($built['unknown'])->not->toBeEmpty()
            ->and(count($trends['table']['rows']))->toBeGreaterThanOrEqual(12)->toBeLessThanOrEqual(13)->and((float) str_replace(',', '', $trends['table']['totals'][1]))->toBeGreaterThan(100000.0)
            ->and($budget->property_id)->toBe($property->id)->and($budget->lines)->toHaveCount(15)->and((float) $budget->lines->where('account_id', Account::query()->where('system_key', 'room_revenue')->value('id'))->sole()->month_12)->toBe(1_680_000.0);
    });

    app(TenantContext::class)->run(tenant('greenvalley'), function (): void {
        expect(Budget::query()->count())->toBe(1)->and(PosOrder::query()->where('order_no', 'like', 'H%')->count())->toBe(0);
    });
});

it('puts Rodela on Enterprise and Green Valley on Professional, with one announcement for everyone (Step 7.3)', function (): void {
    seed(DatabaseSeeder::class);

    $plans = Plan::query()->pluck('code', 'id');

    expect($plans->values()->sort()->values()->all())->toBe(['enterprise', 'professional', 'starter'])
        ->and($plans[tenant('rodela')->plan_id])->toBe('enterprise')->and($plans[tenant('greenvalley')->plan_id])->toBe('professional')
        ->and(Announcement::query()->count())->toBe(1);
    app(TenantContext::class)->run(tenant('rodela'), function (): void {
        expect(app(ModuleAccess::class)->enabled('restaurant'))->toBeTrue()->and(app(PlanLimits::class)->limit('max_rooms'))->toBeNull();
    });
});

it('gives Rodela a paid yearly subscription and Green Valley a trial in its last days (Step 7.4)', function (): void {
    seed(DatabaseSeeder::class);

    $rodela = Subscription::currentFor(tenant('rodela')->id);
    $valley = Subscription::currentFor(tenant('greenvalley')->id);

    expect($rodela?->status)->toBe(SubscriptionStatus::Active)->and($rodela?->billing_period)->toBe(BillingPeriod::Yearly)
        ->and(PlatformInvoice::query()->where('tenant_id', tenant('rodela')->id)->sole()->status)->toBe(PlatformInvoiceStatus::Paid)
        ->and($valley?->status)->toBe(SubscriptionStatus::Trialing)->and(tenant('greenvalley')->status)->toBe(TenantStatus::Trial)
        ->and(app(TenantContext::class)->run(tenant('greenvalley'), fn () => app(SubscriptionAccount::class)->current()?->remindTrial))->toBeTrue();
});
