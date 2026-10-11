<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Inventory\Actions\ImportItems;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\Unit;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Restaurant\Models\Outlet;

/**
 * Inventory demo (Step 5.2): standard units, five stores at Rodela (main, kitchen, bar, housekeeping, maintenance) and
 * about 150 items in food, beverage, housekeeping, maintenance and operating categories, loaded through the CSV
 * import, with box and sack conversions, levels per store and the restaurant outlets linked to their stores.
 * Green Valley gets a main and a kitchen store and a short list.
 */
final class DemoInventory
{
    /**
     * category path => lines "name|unit|reorder level|standard cost" (suffix "|e" = tracks expiry).
     *
     * @var array<string, list<string>>
     */
    private const array CATALOGUE = [
        'Food > Produce' => ['Potato|kg|40|45', 'Onion|kg|40|60', 'Garlic|kg|8|220', 'Ginger|kg|8|180', 'Green chilli|kg|5|140', 'Tomato|kg|20|70', 'Cucumber|kg|15|50', 'Carrot|kg|15|65', 'Cabbage|kg|15|40', 'Cauliflower|kg|10|60',
            'Brinjal|kg|10|70', 'Pumpkin|kg|10|45', 'Spinach|kg|8|50|e', 'Coriander leaves|kg|3|180|e', 'Lemon|pcs|60|8', 'Papaya|kg|10|55', 'Pineapple|pcs|10|90', 'Watermelon|kg|20|35', 'Mango|kg|20|140', 'Banana|dozen|10|80'],
        'Food > Meat and fish' => ['Chicken whole|kg|40|260|e', 'Chicken breast|kg|20|340|e', 'Beef boneless|kg|25|850|e', 'Mutton|kg|15|1100|e', 'Hilsa fish|kg|15|1800|e', 'Tiger prawn|kg|10|1500|e', 'Rui fish|kg|15|420|e', 'Pomfret|kg|8|900|e',
            'Sea bass|kg|8|1100|e', 'Crab|kg|5|700|e', 'Eggs|pcs|300|14|e', 'Duck|kg|8|480|e'],
        'Food > Dairy' => ['Milk|l|40|95|e', 'Butter|kg|8|780|e', 'Ghee|kg|6|900', 'Cheddar cheese|kg|5|1250|e', 'Mozzarella|kg|5|1100|e', 'Yoghurt|kg|15|180|e', 'Cream|l|8|520|e', 'Paneer|kg|5|650|e'],
        'Food > Dry goods' => ['Basmati rice|kg|100|165', 'Miniket rice|kg|200|78', 'Wheat flour|kg|60|58', 'Sugar|kg|60|120', 'Salt|kg|30|40', 'Masoor lentil|kg|30|130', 'Moong lentil|kg|30|150', 'Chickpeas|kg|20|120',
            'Soybean oil|l|80|190', 'Mustard oil|l|20|320', 'Pasta|kg|20|160', 'Instant noodles|pack|100|25', 'Bread crumbs|kg|8|180', 'Cornflour|kg|10|110', 'Rolled oats|kg|10|220', 'Tomato ketchup|bottle|30|140'],
        'Food > Spices' => ['Turmeric powder|kg|5|320', 'Chilli powder|kg|6|420', 'Cumin|kg|4|650', 'Coriander powder|kg|5|360', 'Garam masala|kg|3|900', 'Black pepper|kg|2|1500', 'Cardamom|kg|1|4200', 'Cinnamon|kg|1|900',
            'Cloves|kg|1|2200', 'Bay leaf|kg|1|260', 'Mustard seed|kg|3|180', 'Fennel seed|kg|2|480', 'Panch phoron|kg|2|420', 'Dried red chilli|kg|3|380'],
        'Beverages > Soft drinks' => ['Coca-Cola 500 ml|bottle|120|35', 'Sprite 500 ml|bottle|120|35', 'Fanta 500 ml|bottle|60|35', '7Up 500 ml|bottle|60|35', 'Mountain Dew 500 ml|bottle|36|35', 'Mineral water 500 ml|bottle|300|14',
            'Mineral water 1.5 l|bottle|120|28', 'Soda water 330 ml|can|60|30'],
        'Beverages > Juices and mixers' => ['Orange juice|l|20|260', 'Mango juice|l|20|220', 'Apple juice|l|15|280', 'Lime cordial|bottle|10|420', 'Tonic water|bottle|36|55', 'Ginger ale|bottle|36|55', 'Coconut water|pcs|40|60'],
        'Beverages > Tea and coffee' => ['Tea leaves|kg|10|620', 'Green tea|box|10|380', 'Coffee beans|kg|8|1800', 'Instant coffee|jar|15|520', 'Hot chocolate mix|kg|3|1100', 'Sugar sachets|box|10|180'],
        'Housekeeping > Linen' => ['Bath towel|pcs|150|450', 'Hand towel|pcs|150|220', 'Face towel|pcs|150|120', 'Pool towel|pcs|80|520', 'Bed sheet king|pcs|100|1100', 'Bed sheet single|pcs|60|800', 'Duvet cover|pcs|80|1300',
            'Pillow case|pcs|200|260', 'Bathrobe|pcs|40|1800', 'Blanket|pcs|40|1500', 'Mattress protector|pcs|40|900', 'Table cloth|pcs|40|650', 'Cloth napkin|pcs|200|90'],
        'Housekeeping > Guest amenities' => ['Soap bar 25 g|pcs|600|9', 'Shampoo 30 ml|pcs|500|12', 'Conditioner 30 ml|pcs|400|12', 'Body lotion 30 ml|pcs|400|14', 'Shower gel 30 ml|pcs|400|12', 'Dental kit|pcs|300|18', 'Shaving kit|pcs|150|25',
            'Comb|pcs|200|6', 'Slippers|pair|200|45', 'Sewing kit|pcs|150|10', 'Shower cap|pcs|300|4', 'Cotton buds|pack|200|8', 'Facial tissue box|box|150|40', 'Toilet roll|roll|400|22', 'Sanitary bag|pcs|300|3',
            'Complimentary water 330 ml|bottle|600|10', 'Tea and coffee sachets|pack|250|15', 'Do-not-disturb sign|pcs|60|35'],
        'Housekeeping > Cleaning supplies' => ['Floor cleaner|l|40|150', 'Glass cleaner|l|20|170', 'Bleach|l|30|90', 'Toilet cleaner|l|30|130', 'Laundry detergent|kg|60|140', 'Fabric softener|l|30|180', 'Disinfectant|l|30|240',
            'Mop head|pcs|20|220', 'Broom|pcs|15|130', 'Dust pan|pcs|10|90', 'Rubber gloves|pair|30|70', 'Garbage bags|roll|60|110', 'Scouring sponge|pcs|100|12', 'Microfibre cloth|pcs|100|45', 'Air freshener|can|40|260', 'Dish wash liquid|l|30|120'],
        'Maintenance > Electrical' => ['LED bulb 9 W|pcs|60|130', 'LED tube 18 W|pcs|40|240', 'Wall switch|pcs|30|70', 'Wall socket|pcs|30|110', 'Electric cable 2.5 mm|m|200|65', 'MCB 16 A|pcs|10|420', 'Fan capacitor|pcs|20|90', 'AA battery|pcs|120|35'],
        'Maintenance > Tools and hardware' => ['Screwdriver set|pcs|4|650', 'Hammer|pcs|3|420', 'White paint|l|40|520', 'Paint brush|pcs|20|90', 'Screws assorted|box|10|240', 'Nails|kg|10|130', 'Padlock|pcs|10|220', 'AC filter|pcs|20|350', 'AC gas R410A|can|6|3800'],
    ];

    /** Storage by top-level category. */
    private const array HOME = ['Food' => 'KITCHEN', 'Beverages' => 'BAR', 'Housekeeping' => 'HK', 'Maintenance' => 'MNT', 'Operating supplies' => 'MAIN'];

    public static function seed(Tenant $tenant, int $propertyId, bool $full = true): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $full): void {
            app(SeedStandardUnits::class)->handle();

            if (Item::query()->exists()) {
                return;
            }

            $departments = [];

            foreach (app(DepartmentDirectory::class)->all(false) as $department) {
                $departments[$department->code] = $department->id;
            }

            $stores = [];
            $layout = $full
                ? [['MAIN', 'Main store', 'main', 'STR'], ['KITCHEN', 'Kitchen store', 'kitchen', 'FBP'], ['BAR', 'Bar store', 'bar', 'FBS'], ['HK', 'Housekeeping store', 'housekeeping', 'HK'], ['MNT', 'Maintenance store', 'maintenance', 'ENG']]
                : [['MAIN', 'Main store', 'main', 'STR'], ['KITCHEN', 'Valley kitchen store', 'kitchen', 'FBP']];

            foreach ($layout as [$code, $name, $kind, $department]) {
                $stores[$code] = app(SaveStore::class)->handle($propertyId, null, ['code' => $code, 'name' => $name, 'kind' => $kind, 'department_id' => $departments[$department] ?? null]);
            }

            $catalogue = $full ? self::CATALOGUE : array_intersect_key(self::CATALOGUE, array_flip(['Food > Produce', 'Food > Dairy', 'Food > Dry goods', 'Beverages > Soft drinks']));
            $rows = ['sku,name,category,base_unit,reorder_level,standard_cost,expiry'];
            $counter = ['Food' => 0, 'Beverages' => 0, 'Housekeeping' => 0, 'Maintenance' => 0, 'Operating supplies' => 0];
            $prefix = ['Food' => 'FD', 'Beverages' => 'BV', 'Housekeeping' => 'HK', 'Maintenance' => 'MT', 'Operating supplies' => 'OP'];

            foreach ($catalogue as $path => $lines) {
                $top = trim(explode('>', $path)[0]);

                foreach ($lines as $line) {
                    [$name, $unit, $reorder, $cost] = array_slice(explode('|', $line), 0, 4);
                    $rows[] = implode(',', [$prefix[$top].'-'.str_pad((string) ++$counter[$top], 3, '0', STR_PAD_LEFT), '"'.$name.'"', '"'.$path.'"', $unit, $reorder, $cost, str_ends_with($line, '|e') ? 'yes' : 'no']);
                }
            }

            app(ImportItems::class)->handle(implode("\n", $rows));

            // Levels per store: each item's par is three times its reorder level where it is kept.
            foreach (Item::query()->with('category.parent')->get() as $item) {
                $parent = $item->category->parent;
                $top = $parent instanceof ItemCategory ? $parent->name : $item->category->name;
                $store = $stores[self::HOME[$top] ?? 'MAIN'] ?? $stores['MAIN'];
                ItemStoreLevel::query()->create(['item_id' => $item->id, 'store_id' => $store->id, 'par_level' => bcmul((string) $item->reorder_level, '3', 4), 'reorder_level' => $item->reorder_level]);
            }

            // Sacks, cartons and trays: purchase units with their own conversions.
            foreach ([
                'Basmati rice' => ['sack', '25'], 'Miniket rice' => ['sack', '50'], 'Wheat flour' => ['sack', '50'], 'Sugar' => ['sack', '50'], 'Eggs' => ['tray', '30'], 'Mineral water 500 ml' => ['carton', '24'],
                'Coca-Cola 500 ml' => ['carton', '24'], 'Sprite 500 ml' => ['carton', '24'], 'Soap bar 25 g' => ['box', '100'], 'Toilet roll' => ['carton', '48'], 'LED bulb 9 W' => ['box', '20'], 'Garbage bags' => ['carton', '20'],
            ] as $name => [$unit, $factor]) {
                $item = Item::query()->where('name', $name)->first();
                $pack = Unit::query()->where('code', $unit)->first();

                if ($item instanceof Item && $pack instanceof Unit) {
                    app(SaveItem::class)->handle(
                        $item, [...$item->only(['item_category_id', 'sku', 'name', 'barcode', 'base_unit_id', 'is_stockable', 'track_batches', 'track_expiry', 'reorder_level', 'reorder_qty', 'standard_cost', 'description', 'is_active']), 'purchase_unit_id' => $pack->id],
                        [['unit_id' => $pack->id, 'factor' => $factor]], $item->levels->map(fn (ItemStoreLevel $level): array => ['store_id' => $level->store_id, 'par_level' => $level->par_level !== null ? (string) $level->par_level : null, 'reorder_level' => $level->reorder_level !== null ? (string) $level->reorder_level : null])->all(),
                    );
                }
            }

            // Restaurant outlets draw from their stores.
            foreach (['MR' => 'KITCHEN', 'PB' => 'BAR', 'RS' => 'KITCHEN'] as $outlet => $store) {
                if (isset($stores[$store])) {
                    Outlet::query()->where('property_id', $propertyId)->where('code', $outlet)->update(['store_id' => $stores[$store]->id]);
                }
            }

        });
    }
}
