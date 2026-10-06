<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Contracts\RoleDirectory;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\AddOrderLine;
use Modules\Restaurant\Actions\AdvanceDelivery;
use Modules\Restaurant\Actions\CompBill;
use Modules\Restaurant\Actions\DiscountOrder;
use Modules\Restaurant\Actions\OpenOrder;
use Modules\Restaurant\Actions\OpenPosSession;
use Modules\Restaurant\Actions\PrintBill;
use Modules\Restaurant\Actions\RedeemMealPlan;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Actions\SaveDiningArea;
use Modules\Restaurant\Actions\SaveDiningTable;
use Modules\Restaurant\Actions\SaveDiscountLimits;
use Modules\Restaurant\Actions\SaveFloorPlan;
use Modules\Restaurant\Actions\SaveOutlet;
use Modules\Restaurant\Actions\SavePrinter;
use Modules\Restaurant\Actions\SaveStation;
use Modules\Restaurant\Actions\SaveTableReservation;
use Modules\Restaurant\Actions\SendOrder;
use Modules\Restaurant\Actions\SyncOutletAccess;
use Modules\Restaurant\Actions\TakePayment;
use Modules\Restaurant\Actions\VoidOrderLine;
use Modules\Restaurant\DTOs\ChargeTarget;
use Modules\Restaurant\DTOs\OrderDestination;
use Modules\Restaurant\Enums\CompReason;
use Modules\Restaurant\Enums\DeliveryStatus;
use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Enums\SplitMode;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Enums\VoidReason;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MealEntitlementSnapshot;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\SessionCash;

/**
 * Restaurant setup demo (Step 3.1). Rodela: the Main Restaurant (Indoor with 8 tables, Terrace with
 * 4, laid out on the floor plan; Hot kitchen, Grill, Pastry and Bar stations; a captain tablet and a
 * cashier desk), the Pool Bar (4 high tables at the bar counter, a Bar station and a till) and Room
 * Service (no tables, its own kitchen station and order desk), with kitchen, bar and receipt
 * printers, and F&B staff assigned to their outlets. Green Valley: one small Valley Kitchen.
 */
final class DemoRestaurant
{
    private const array HOURS_ALL_DAY = ['open' => '07:00', 'close' => '23:00'];

    private const array HOURS_BAR = ['open' => '11:00', 'close' => '01:00'];

    private const array HOURS_ROOM_SERVICE = ['open' => '00:00', 'close' => '23:59'];

    public static function seed(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            if (Outlet::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $tax = TaxCategory::query()->where('code', 'FNB')->value('id');
            $printers = [];

            foreach ([['Kitchen KOT printer', 'kot'], ['Bar KOT printer', 'kot'], ['Restaurant receipt printer', 'receipt'], ['Pool bar receipt printer', 'receipt']] as [$name, $type]) {
                $printers[$name] = SavePrinter::make()->handle($propertyId, null, ['name' => $name, 'type' => $type, 'connection' => 'browser', 'paper_width_mm' => 80]);
            }

            $main = self::outlet($propertyId, 'MR', 'Main Restaurant', 'restaurant', 'MR', $tax, self::HOURS_ALL_DAY, 'Rodela Eco Resort · Main Restaurant', 'Thank you! Charge to your room at any outlet.');
            self::stations($main, [['Hot kitchen', 'both', $printers['Kitchen KOT printer']], ['Grill', 'display', null], ['Pastry', 'display', null], ['Bar', 'printer', $printers['Bar KOT printer']]]);
            self::terminals($main, [['Captain tablet', null], ['Cashier desk', $printers['Restaurant receipt printer']]]);
            self::floor($main, 'Indoor', [
                ['T1', 2, 'square', 40, 40], ['T2', 2, 'square', 160, 40], ['T3', 4, 'square', 280, 40], ['T4', 4, 'square', 400, 40],
                ['T5', 4, 'round', 40, 200], ['T6', 4, 'round', 200, 200], ['T7', 6, 'rectangle', 360, 200], ['T8', 8, 'rectangle', 600, 200],
            ]);
            self::floor($main, 'Terrace', [['T9', 4, 'round', 60, 60], ['T10', 4, 'round', 240, 60], ['T11', 2, 'square', 420, 60], ['T12', 6, 'rectangle', 560, 60]]);

            $bar = self::outlet($propertyId, 'PB', 'Pool Bar', 'bar', 'PB', $tax, self::HOURS_BAR, 'Rodela Eco Resort · Pool Bar', null);
            self::stations($bar, [['Bar', 'both', $printers['Bar KOT printer']]]);
            self::terminals($bar, [['Pool bar till', $printers['Pool bar receipt printer']]]);
            self::floor($bar, 'Bar counter', [['B1', 2, 'round', 60, 60], ['B2', 2, 'round', 180, 60], ['B3', 2, 'round', 300, 60], ['B4', 4, 'square', 440, 60]]);

            $roomService = self::outlet($propertyId, 'RS', 'Room Service', 'room_service', 'RS', $tax, self::HOURS_ROOM_SERVICE, 'Rodela Eco Resort · In-room dining', null);
            self::stations($roomService, [['Room service kitchen', 'both', $printers['Kitchen KOT printer']]]);
            self::terminals($roomService, [['Room service desk', $printers['Restaurant receipt printer']]]);

            // F&B staff: who works where (the GM and Owner see every outlet through their role).
            $users = collect(app(UserDirectory::class)->all())->keyBy(fn (UserSummary $user): string => strtok($user->email, '@'));
            $assign = ['fnb' => [$main, $bar, $roomService], 'cashier' => [$main, $bar, $roomService], 'waiter' => [$main, $bar], 'chef' => [$main, $roomService], 'bartender' => [$bar]];
            $assignments = [];

            foreach ($assign as $mailbox => $outlets) {
                foreach ($outlets as $outlet) {
                    if ($users->has($mailbox) && str_ends_with($users[$mailbox]->email, '@'.$domain)) {
                        $assignments[$outlet->id][] = $users[$mailbox]->id;
                    }
                }
            }

            SyncOutletAccess::make()->handle($propertyId, $users->only(array_keys($assign))->map(fn (UserSummary $user): int => $user->id)->values()->all(), $assignments);
        });
    }

    /**
     * Step 3.3: POS PINs for the F&B staff and a known device token for the Main Restaurant's cashier
     * desk, so a tablet can be registered at once (local demo only).
     */
    public const string DEMO_DEVICE_TOKEN = 'DEMO-CASH-DESK-0001';

    /**
     * mailbox => PIN
     *
     * @var array<string, string>
     */
    /** The demo kitchen display token of the Main Restaurant's Hot kitchen (Step 3.5). */
    public const string DEMO_DISPLAY_TOKEN = 'DEMO-HOT-KITCHEN-0001';

    public const array DEMO_PINS = ['waiter' => '1111', 'cashier' => '2222', 'bartender' => '3333', 'chef' => '4444', 'gm' => '8888', 'fnb' => '9999'];

    public static function pos(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            $users = collect(app(UserDirectory::class)->all())->keyBy('email');

            foreach (self::DEMO_PINS as $mailbox => $pin) {
                $user = $users->get($mailbox.'@'.$domain);

                if ($user !== null && ! app(PosPins::class)->has($user->id)) {
                    app(PosPins::class)->set($user->id, $pin);
                }
            }

            PosTerminal::query()->where('property_id', $propertyId)->where('name', 'Cashier desk')
                ->update(['device_token' => hash('sha256', self::DEMO_DEVICE_TOKEN)]);

            // Step 3.5: the Main Restaurant's hot kitchen screen.
            KitchenStation::query()->where('property_id', $propertyId)->where('name', 'Hot kitchen')
                ->whereIn('outlet_id', Outlet::query()->where('code', 'MR')->select('id'))
                ->update(['display_token' => hash('sha256', self::DEMO_DISPLAY_TOKEN)]);
        });
    }

    /**
     * Step 3.6: discount limits (waiter and bartender 5%, cashier 10%; managers any), and a settled bill:
     * the cashier's session is open at the Main Restaurant's cashier desk (3,000 float) and table T6's
     * lunch was paid by card.
     */
    public static function bills(Tenant $tenant, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($domain): void {
            $roles = collect(app(RoleDirectory::class)->all())->keyBy('defaultRole');
            $limits = [];

            foreach ([[DefaultRole::Waiter, '5'], [DefaultRole::Bartender, '5'], [DefaultRole::OutletCashier, '10']] as [$role, $percent]) {
                if ($roles->has($role->value)) {
                    $limits[$roles->get($role->value)->id] = $percent;
                }
            }

            SaveDiscountLimits::make()->handle($limits);

            $users = collect(app(UserDirectory::class)->all())->keyBy('email');
            $cashier = $users->get('cashier@'.$domain);
            $waiter = $users->get('waiter@'.$domain);
            $outlet = Outlet::query()->where('code', 'MR')->firstOrFail();
            $terminal = PosTerminal::query()->where('outlet_id', $outlet->id)->where('name', 'Cashier desk')->first();
            $table = DiningTable::query()->where('outlet_id', $outlet->id)->where('number', 'T6')->first();

            if (! $cashier instanceof UserSummary || ! $waiter instanceof UserSummary || ! $terminal instanceof PosTerminal || ! $table instanceof DiningTable
                || PosBill::query()->where('outlet_id', $outlet->id)->exists()) {
                return; // seeded already
            }

            $session = OpenPosSession::make()->handle($terminal, $cashier->id, '3000');
            $order = OpenOrder::make()->handle($outlet, OrderType::DineIn, $waiter->id, $table->id, 2);
            $item = fn (string $code): int => MenuItem::query()->where('code', $code)->value('id');
            AddOrderLine::make()->handle($order, ['item_id' => $item('BD06')], $waiter->id, false);
            AddOrderLine::make()->handle($order, ['item_id' => $item('PR05'), 'quantity' => 2], $waiter->id, false);
            AddOrderLine::make()->handle($order, ['item_id' => $item('BD05')], $waiter->id, false);
            SendOrder::make()->handle($order, $waiter->id);
            [$bill] = PrintBill::make()->handle($order, SplitMode::None, [], $waiter->id);
            TakePayment::make()->handle($bill, PaymentMethod::Card, (string) $bill->grand_total, '50', null, '4417', $session, $cashier->id);
        });
    }

    /**
     * Step 3.7: room 402's guest (Bed & Breakfast) had breakfast for two at the Main Restaurant (table T3):
     * the breakfast is redeemed on the meal plan at nothing, and the coffees are charged to the room (on the
     * guest's folio, linked to the receipt).
     */
    public static function packages(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            $users = collect(app(UserDirectory::class)->all())->keyBy('email');
            $cashier = $users->get('cashier@'.$domain);
            $waiter = $users->get('waiter@'.$domain);
            $outlet = Outlet::query()->where('code', 'MR')->firstOrFail();
            $table = DiningTable::query()->where('outlet_id', $outlet->id)->where('status', TableStatus::Available->value)->orderByRaw("number = 'T3' desc")->orderBy('id')->first();
            $session = PosSession::query()->where('outlet_id', $outlet->id)->where('status', PosSessionStatus::Open->value)->first();
            $stay = collect(app(FolioPostingContract::class)->chargeableStays($propertyId, '402'))->first();

            if (! $cashier instanceof UserSummary || ! $waiter instanceof UserSummary || ! $table instanceof DiningTable || ! $session instanceof PosSession
                || ! $stay instanceof ChargeableStay || PackageRedemption::query()->exists()) {
                return; // seeded already, or nothing to seed it on
            }

            $order = OpenOrder::make()->handle($outlet, OrderType::DineIn, $waiter->id, $table->id, 2);
            // Breakfast is on the menu only until 10:30: the plates are put on the order as the POS would.
            $paratha = MenuItem::query()->where('code', 'BF01')->firstOrFail();
            $price = (string) OutletMenuItem::query()->where('outlet_id', $outlet->id)->where('menu_item_id', $paratha->id)->value('price');
            PosOrderLine::query()->create([
                'property_id' => $order->property_id, 'pos_order_id' => $order->id, 'menu_item_id' => $paratha->id, 'name_snapshot' => $paratha->translated('name'),
                'quantity' => 2, 'unit_price' => $price, 'line_total' => (string) BigDecimal::of($price)->multipliedBy(2)->toScale(2), 'course' => $paratha->course,
                'kitchen_station_id' => KitchenStation::query()->where('outlet_id', $outlet->id)->where('name', 'Hot kitchen')->value('id'), 'status' => OrderLineStatus::Pending,
                'added_by' => $waiter->id,
            ]);
            AddOrderLine::make()->handle($order, ['item_id' => MenuItem::query()->where('code', 'HD01')->value('id'),
                'variant_id' => MenuItemVariant::query()->where('menu_item_id', MenuItem::query()->where('code', 'HD01')->value('id'))->where('name', 'Regular')->value('id'), 'quantity' => 2],
                $waiter->id, false);
            RedeemMealPlan::make()->handle($order, $stay->reservationId, MealPeriod::Breakfast, 2, 0, $waiter->id, false);
            SendOrder::make()->handle($order, $waiter->id);
            [$bill] = PrintBill::make()->handle($order, SplitMode::None, [], $waiter->id);
            TakePayment::make()->handle($bill, PaymentMethod::RoomCharge, (string) $bill->grand_total, '0', null, null, $session, $cashier->id, new ChargeTarget($stay->reservationId));
        });
    }

    /**
     * Step 3.8: a room-service order to room 402 delivered and charged to the room (Room Service desk), a
     * pool delivery on its way (Pool Bar), a staff meal (Main Restaurant, complimentary), table reservations
     * (today for the in-house guest, tomorrow for two parties), and yesterday's trading for the reports: Pool
     * Bar sales (card and cash, one voided item, one discount) and the 402 guest's breakfast on the meal plan.
     */
    public static function service(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            $users = collect(app(UserDirectory::class)->all())->keyBy('email');
            $cashier = $users->get('cashier@'.$domain);
            $waiter = $users->get('waiter@'.$domain);
            $manager = $users->get('fnb@'.$domain);
            $stay = collect(app(FolioPostingContract::class)->chargeableStays($propertyId, '402'))->first();
            $outlets = Outlet::query()->whereIn('code', ['MR', 'PB', 'RS'])->get()->keyBy('code');
            $mainSession = PosSession::query()->where('outlet_id', $outlets['MR']->id ?? 0)->where('status', PosSessionStatus::Open->value)->first();

            if (! $cashier instanceof UserSummary || ! $waiter instanceof UserSummary || ! $manager instanceof UserSummary || ! $stay instanceof ChargeableStay || ! $mainSession instanceof PosSession
                || $outlets->count() < 3 || TableReservation::query()->exists()) {
                return; // seeded already, or nothing to seed it on
            }

            $today = (string) app(PropertyDirectory::class)->find($propertyId)?->businessDate;
            $yesterday = CarbonImmutable::parse($today)->subDay()->toDateString();
            $item = fn (string $code): int => (int) MenuItem::query()->where('code', $code)->value('id');
            $add = fn (PosOrder $order, string $code, int $quantity = 1, array $more = []): PosOrderLine => AddOrderLine::make()->handle($order, ['item_id' => $item($code), 'quantity' => $quantity, ...$more], $waiter->id, false);
            $session = fn (Outlet $outlet): PosSession => OpenPosSession::make()->handle(PosTerminal::query()->where('outlet_id', $outlet->id)->orderBy('id')->firstOrFail(), $cashier->id, '1000');

            // Room service to 402, charged to the room, delivered.
            $roomService = $session($outlets['RS']);
            $order = OpenOrder::make()->handle($outlets['RS'], OrderType::RoomService, $waiter->id, null, 1, new OrderDestination($stay->reservationId));
            $add($order, 'BD05');
            $add($order, 'PR05', 2);
            SendOrder::make()->handle($order, $waiter->id);
            [$bill] = PrintBill::make()->handle($order, SplitMode::None, [], $waiter->id);
            TakePayment::make()->handle($bill, PaymentMethod::RoomCharge, (string) $bill->grand_total, '0', null, null, $roomService, $cashier->id, new ChargeTarget($stay->reservationId));
            AdvanceDelivery::make()->handle($order, DeliveryStatus::OutForDelivery);
            AdvanceDelivery::make()->handle($order, DeliveryStatus::Delivered);

            // A pool delivery still being prepared.
            $pool = OpenOrder::make()->handle($outlets['PB'], OrderType::LocationDelivery, $waiter->id, null, 2, new OrderDestination(null, 'Pool deck, bed 4'));
            $add($pool, 'SD02', 2);
            SendOrder::make()->handle($pool, $waiter->id);

            // A staff meal, settled as complimentary.
            $meal = OpenOrder::make()->handle($outlets['MR'], OrderType::StaffMeal, $waiter->id, null, 4, new OrderDestination(null, null, 'Kitchen team'));
            $add($meal, 'PR04', 4);
            SendOrder::make()->handle($meal, $waiter->id);
            [$mealBill] = PrintBill::make()->handle($meal, SplitMode::None, [], $waiter->id);
            CompBill::make()->handle($mealBill, CompReason::StaffMeal, null, $mainSession, $cashier->id, true);

            // Table reservations: tonight for the in-house guest, tomorrow for two parties.
            $table = fn (string $number): int => (int) DiningTable::query()->where('outlet_id', $outlets['MR']->id)->where('number', $number)->value('id');
            $tomorrow = CarbonImmutable::parse($today)->addDay()->toDateString();
            $book = fn (string $date, string $time, int $party, string $number, array $who, array $more = []): TableReservation => SaveTableReservation::make()->handle($outlets['MR'], null,
                ['date' => $date, 'time' => $time, 'party_size' => $party, 'dining_table_id' => $table($number), ...$who, ...$more], $manager->id);
            $book($today, '20:00', 2, 'T2', ['reservation_id' => $stay->reservationId], ['occasion' => 'Anniversary', 'notes' => 'Quiet corner, please']);
            $book($tomorrow, '19:00', 4, 'T5', ['customer_name' => 'Mr & Mrs Karim', 'phone' => '01711-555010'], ['occasion' => 'Birthday']);
            $book($tomorrow, '20:00', 6, 'T8', ['customer_name' => 'Dhaka Bank dinner', 'phone' => '01819-555022'], ['notes' => 'Vegetarian options for two']);

            // Yesterday at the Pool Bar: sales by card and cash, a discount and a voided item.
            $poolSession = $session($outlets['PB']);
            $sell = function (array $lines, PaymentMethod $method, ?array $discount = null, bool $void = false) use ($outlets, $waiter, $cashier, $manager, $poolSession, $add): PosOrder {
                $order = OpenOrder::make()->handle($outlets['PB'], OrderType::Takeaway, $waiter->id);

                foreach ($lines as [$code, $quantity]) {
                    $add($order, $code, $quantity);
                }

                SendOrder::make()->handle($order, $waiter->id);

                if ($void) {
                    VoidOrderLine::make()->handle($order->lines()->firstOrFail(), VoidReason::QualityIssue, 'Warm drink', false, $manager->id, true);
                }

                if ($discount !== null) {
                    DiscountOrder::make()->handle($order, null, DiscountType::Percent, $discount[0], $discount[1], $manager->id);
                }

                [$bill] = PrintBill::make()->handle($order, SplitMode::None, [], $waiter->id);
                TakePayment::make()->handle($bill, $method, (string) $bill->grand_total, '0', $method === PaymentMethod::Cash ? '2000' : null, $method === PaymentMethod::Card ? '7731' : null, $poolSession, $cashier->id);

                return $order;
            };
            $sold = [
                $sell([['SD02', 2], ['ST03', 1]], PaymentMethod::Card),
                $sell([['ST08', 3]], PaymentMethod::Cash, ['10', 'Regular guest']),
                $sell([['SD02', 1], ['PR03', 1]], PaymentMethod::Card, null, true),
            ];

            // The 402 guest's breakfast yesterday: one of the two included covers taken at the Main Restaurant.
            $breakfast = OpenOrder::make()->handle($outlets['MR'], OrderType::DineIn, $waiter->id, $table('T7'), 1);
            // Breakfast is on the menu only until 10:30: the plate is put on the order as the POS would.
            $paratha = MenuItem::query()->where('code', 'BF01')->firstOrFail();
            $price = (string) OutletMenuItem::query()->where('outlet_id', $outlets['MR']->id)->where('menu_item_id', $paratha->id)->value('price');
            PosOrderLine::query()->create([
                'property_id' => $breakfast->property_id, 'pos_order_id' => $breakfast->id, 'menu_item_id' => $paratha->id, 'name_snapshot' => $paratha->translated('name'),
                'quantity' => 1, 'unit_price' => $price, 'line_total' => $price, 'course' => $paratha->course, 'status' => OrderLineStatus::Pending, 'added_by' => $waiter->id,
                'kitchen_station_id' => KitchenStation::query()->where('outlet_id', $outlets['MR']->id)->where('name', 'Hot kitchen')->value('id'),
            ]);
            SendOrder::make()->handle($breakfast, $waiter->id);
            RedeemMealPlan::make()->handle($breakfast, $stay->reservationId, MealPeriod::Breakfast, 1, 0, $waiter->id, true);
            PrintBill::make()->handle($breakfast, SplitMode::None, [], $waiter->id);
            MealEntitlementSnapshot::query()->create(['property_id' => $propertyId, 'business_date' => $yesterday, 'reservation_id' => $stay->reservationId, 'meal_period' => MealPeriod::Breakfast, 'covers' => 2]);

            // Those sales happened yesterday: move their dates (and the pool session) back a day.
            $orderIds = [...array_map(fn (PosOrder $order): int => $order->id, $sold), $breakfast->id];
            DB::table('pos_orders')->whereIn('id', $orderIds)->update(['business_date' => $yesterday]);
            DB::table('pos_bills')->whereIn('pos_order_id', $orderIds)->update(['business_date' => $yesterday]);
            DB::table('pos_payments')->whereIn('pos_bill_id', DB::table('pos_bills')->whereIn('pos_order_id', $orderIds)->select('id'))->update(['business_date' => $yesterday]);
            DB::table('package_redemptions')->where('pos_order_id', $breakfast->id)->update(['business_date' => $yesterday]);
            [$received] = app(SessionCash::class)->cash($poolSession);
            DB::table('pos_sessions')->where('id', $poolSession->id)->update([
                'business_date' => $yesterday, 'status' => PosSessionStatus::Closed->value, 'open_terminal_id' => null, 'closed_by' => $cashier->id, 'closed_at' => now(),
                'cash_received' => $received, 'cash_refunded' => '0.00', 'expected_cash' => (string) BigDecimal::of($poolSession->opening_float)->plus($received)->toScale(2),
                'counted_cash' => (string) BigDecimal::of($poolSession->opening_float)->plus($received)->toScale(2), 'cash_variance' => '0.00',
            ]);
        });
    }

    /**
     * Step 3.4: an open order at table T4 of the Main Restaurant for 2 covers, taken by the waiter: mains
     * and drinks sent (a ticket to the hot kitchen and one to the bar), desserts held until fired.
     */
    public static function orders(Tenant $tenant, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($domain): void {
            $outlet = Outlet::query()->where('code', 'MR')->firstOrFail();
            $table = DiningTable::query()->where('outlet_id', $outlet->id)->where('number', 'T4')->firstOrFail();
            $waiter = collect(app(UserDirectory::class)->all())->firstWhere('email', 'waiter@'.$domain);
            $item = fn (string $code): MenuItem => MenuItem::query()->where('code', $code)->firstOrFail();
            $variant = fn (string $code, string $name): ?int => MenuItemVariant::query()->where('menu_item_id', $item($code)->id)->where('name', $name)->value('id');
            $modifier = fn (string $group, string $name): ?int => Modifier::query()->where('name', $name)
                ->whereIn('modifier_group_id', ModifierGroup::query()->where('name', $group)->select('id'))->value('id');

            if (! $waiter instanceof UserSummary || PosOrder::query()->where('outlet_id', $outlet->id)->exists()) {
                return; // seeded already
            }

            $order = OpenOrder::make()->handle($outlet, OrderType::DineIn, $waiter->id, $table->id, 2);
            $add = fn (array $line): PosOrderLine => AddOrderLine::make()->handle($order, $line, $waiter->id, false);
            $add(['item_id' => $item('BD01')->id, 'variant_id' => $variant('BD01', 'Full'), 'modifier_ids' => [$modifier('Spice level', 'Medium')], 'seat' => 1]);
            $add(['item_id' => $item('BD04')->id, 'seat' => 2, 'notes' => 'Less mustard']);
            $add(['item_id' => $item('PR06')->id, 'variant_id' => $variant('PR06', 'Butter'), 'quantity' => 2]);
            $add(['item_id' => $item('MK01')->id, 'quantity' => 2]);
            $add(['item_id' => $item('DS02')->id, 'quantity' => 2, 'held' => true]);
            SendOrder::make()->handle($order, $waiter->id);
        });
    }

    /**
     * Green Valley's one outlet: the Valley Kitchen, 6 tables in the dining hall.
     */
    public static function small(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (Outlet::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $printer = SavePrinter::make()->handle($propertyId, null, ['name' => 'Kitchen printer', 'type' => 'kot', 'connection' => 'browser', 'paper_width_mm' => 80]);
            $kitchen = self::outlet($propertyId, 'VK', 'Valley Kitchen', 'restaurant', 'VK', TaxCategory::query()->where('code', 'FNB')->value('id'), self::HOURS_ALL_DAY, 'Green Valley Resort', null);
            self::stations($kitchen, [['Kitchen', 'both', $printer]]);
            self::terminals($kitchen, [['Counter', null]]);
            self::floor($kitchen, 'Dining hall', [['1', 4, 'square', 40, 40], ['2', 4, 'square', 180, 40], ['3', 4, 'square', 320, 40], ['4', 2, 'round', 40, 200], ['5', 2, 'round', 180, 200], ['6', 8, 'rectangle', 320, 200]]);
        });
    }

    /**
     * @param  array{open: string, close: string}  $hours
     */
    private static function outlet(int $propertyId, string $code, string $name, string $type, string $prefix, mixed $tax, array $hours, ?string $header, ?string $footer): Outlet
    {
        return SaveOutlet::make()->handle($propertyId, null, [
            'code' => $code, 'name' => $name, 'type' => $type, 'prices_include_tax' => false, 'default_tax_category_id' => is_numeric($tax) ? (int) $tax : null,
            'bill_prefix' => $prefix, 'receipt_header' => $header, 'receipt_footer' => $footer, 'is_active' => true,
            'opening_hours' => array_fill_keys(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], $hours),
        ]);
    }

    /**
     * @param  list<array{string, string, Printer|null}>  $stations
     */
    private static function stations(Outlet $outlet, array $stations): void
    {
        foreach ($stations as $order => [$name, $output, $printer]) {
            SaveStation::make()->handle($outlet, null, ['name' => $name, 'output' => $output, 'printer_id' => $printer?->id, 'sort_order' => $order]);
        }
    }

    /**
     * @param  list<array{string, Printer|null}>  $terminals
     */
    private static function terminals(Outlet $outlet, array $terminals): void
    {
        foreach ($terminals as [$name, $printer]) {
            RegisterTerminal::make()->handle($outlet, $name, $printer?->id);
        }
    }

    /**
     * An area with its tables at the given positions.
     *
     * @param  list<array{string, int, string, int, int}>  $tables  number, seats, shape, x, y
     */
    private static function floor(Outlet $outlet, string $areaName, array $tables): DiningArea
    {
        $area = SaveDiningArea::make()->handle($outlet, null, $areaName);
        $positions = [];

        foreach ($tables as [$number, $seats, $shape, $x, $y]) {
            $table = SaveDiningTable::make()->handle($area, null, $number, $seats, TableShape::from($shape));
            $positions[] = ['id' => $table->id, 'x' => $x, 'y' => $y];
        }

        SaveFloorPlan::make()->handle($area, $positions);

        return $area;
    }
}
