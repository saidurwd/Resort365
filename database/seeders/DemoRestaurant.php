<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Models\TaxCategory;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Actions\SaveDiningArea;
use Modules\Restaurant\Actions\SaveDiningTable;
use Modules\Restaurant\Actions\SaveFloorPlan;
use Modules\Restaurant\Actions\SaveOutlet;
use Modules\Restaurant\Actions\SavePrinter;
use Modules\Restaurant\Actions\SaveStation;
use Modules\Restaurant\Actions\SyncOutletAccess;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Models\Printer;

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
