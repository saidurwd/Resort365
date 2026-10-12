<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Restaurant\Actions\RecordBillConsumption;
use Modules\Restaurant\Actions\RecordWastage;
use Modules\Restaurant\Actions\SaveRecipe;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Services\RecipeBook;

/**
 * Recipes demo (Step 5.7), Rodela only: recipes for about twenty dishes (the beef burger uses a burger-sauce sub-recipe and
 * "extra cheese" adds cheddar), the bottled drinks linked to their inventory items, the stock the settled demo bills used
 * (the comped lunch is booked as a staff meal) and a wastage entry.
 */
final class DemoRecipes
{
    /**
     * dish code => ingredient lines "name|quantity per portion|waste %".
     *
     * @var array<string, list<string>>
     */
    private const array DISHES = [
        'GR04' => ['Beef boneless|0.18|5', 'Wheat flour|0.12|0', 'Cheddar cheese|0.03|0', 'Tomato|0.05|10', 'Onion|0.03|10', '@Burger sauce|0.03|0'],
        'BD01' => ['Chicken whole|0.35|15', 'Onion|0.1|10', 'Tomato|0.08|5', 'Soybean oil|0.03|0', 'Turmeric powder|0.002|0', 'Chilli powder|0.003|0', 'Garlic|0.01|10', 'Ginger|0.01|10'],
        'BD03' => ['Mutton|0.3|10', 'Basmati rice|0.15|0', 'Ghee|0.03|0', 'Onion|0.1|10', 'Yoghurt|0.08|0', 'Garam masala|0.005|0'],
        'BD04' => ['Hilsa fish|0.25|20', 'Mustard oil|0.05|0', 'Mustard seed|0.02|0', 'Green chilli|0.01|5'],
        'BD08' => ['Chicken whole|0.3|15', 'Basmati rice|0.15|0', 'Ghee|0.02|0'],
        'SF02' => ['Tiger prawn|0.2|10', 'Garlic|0.02|10', 'Butter|0.03|0'],
        'GR02' => ['Chicken whole|0.4|15', 'Tomato ketchup|0.1|0'],
        'PR01' => ['Pasta|0.12|0', 'Beef boneless|0.1|5', 'Tomato|0.1|5', 'Onion|0.04|10'],
        'PR03' => ['Miniket rice|0.2|0', 'Chicken breast|0.1|5', 'Eggs|1|0', 'Soybean oil|0.02|0'],
        'PR05' => ['Miniket rice|0.15|0'],
        'PR06' => ['Wheat flour|0.1|0', 'Yoghurt|0.02|0', 'Sugar|0.005|0'],
        'BF05' => ['Eggs|3|0', 'Butter|0.01|0', 'Onion|0.02|10', 'Green chilli|0.005|5'],
        'BF01' => ['Wheat flour|0.1|0', 'Eggs|1|0', 'Soybean oil|0.02|0'],
        'DS02' => ['Milk|0.3|0', 'Sugar|0.05|0'],
        'HD01' => ['Coffee beans|0.015|0', 'Milk|0.1|0', 'Sugar|0.01|0'],
        'HD03' => ['Tea leaves|0.004|0', 'Milk|0.1|0', 'Sugar|0.01|0'],
        'FJ01' => ['Orange juice|0.25|0'],
        'FJ03' => ['Yoghurt|0.15|0', 'Mango|0.15|10', 'Sugar|0.02|0'],
    ];

    /** Bottled drinks sold as bought: menu code => inventory item name. */
    private const array DIRECT = ['SD01' => 'Mineral water 500 ml', 'SD02' => 'Coca-Cola 500 ml', 'SD03' => 'Sprite 500 ml', 'SD04' => 'Soda water 330 ml'];

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            $catalog = app(ItemCatalog::class);
            $itemId = fn (string $name): ?int => ($catalog->search($name, 1)[0] ?? null)?->id;
            $menu = MenuItem::query()->where('property_id', $propertyId)->get()->keyBy('code');

            if ($menu->isEmpty() || Recipe::query()->exists()) {
                return;
            }

            $save = app(SaveRecipe::class);
            $sauce = $save->handle(new NewRecipe($propertyId, RecipeKind::Sub, [
                ['item_id' => $itemId('Tomato ketchup'), 'quantity' => '0.2'], ['item_id' => $itemId('Soybean oil'), 'quantity' => '0.3'], ['item_id' => $itemId('Eggs'), 'quantity' => '6'], ['item_id' => $itemId('Garlic'), 'quantity' => '0.02'],
            ], name: 'Burger sauce', yieldQuantity: '1', yieldUnit: 'kg'));

            foreach (self::DISHES as $code => $lines) {
                if (! $menu->has($code)) {
                    continue;
                }

                $rows = [];

                foreach ($lines as $line) {
                    [$what, $quantity, $waste] = explode('|', $line);
                    $rows[] = str_starts_with($what, '@') ? ['sub_recipe_id' => $sauce?->id, 'quantity' => $quantity, 'waste_percent' => $waste] : ['item_id' => $itemId($what), 'quantity' => $quantity, 'waste_percent' => $waste];
                }

                $save->handle(new NewRecipe($propertyId, RecipeKind::Dish, $rows, menuItemId: $menu[$code]->id));
            }

            $cheese = Modifier::query()->where('name', 'Extra cheese')->first();

            if ($cheese instanceof Modifier) {
                $save->handle(new NewRecipe($propertyId, RecipeKind::Modifier, [['item_id' => $itemId('Cheddar cheese'), 'quantity' => '0.03']], modifierId: $cheese->id));
            }

            foreach (self::DIRECT as $code => $name) {
                if ($menu->has($code) && $itemId($name) !== null) {
                    $menu[$code]->forceFill(['inventory_item_id' => $itemId($name)])->save();
                }
            }

            // An outlet's store may not hold an ingredient its dishes need (the Pool Bar sells fried rice): bring a week's worth in.
            $book = app(RecipeBook::class);
            $stock = app(StockMovementContract::class);
            $settled = PosBill::query()->where('status', BillStatus::Settled->value)->orderBy('id')->get();

            foreach ($settled as $bill) {
                $storeId = Outlet::query()->find($bill->outlet_id)?->store_id;

                foreach (PosBillLine::query()->where('pos_bill_id', $bill->id)->get() as $billLine) {
                    $orderLine = PosOrderLine::query()->find($billLine->pos_order_line_id);
                    $dish = $orderLine instanceof PosOrderLine ? MenuItem::query()->find($orderLine->menu_item_id) : null;

                    if ($storeId === null || ! $dish instanceof MenuItem) {
                        continue;
                    }

                    foreach ($book->portion($dish, $orderLine->menu_item_variant_id, $book->modifierIds($orderLine)) as $ingredient => $quantity) {
                        if (bccomp($stock->balance($storeId, $ingredient)->quantity, bcmul($quantity, '50', 4), 4) < 0) {
                            $cost = $catalog->find($ingredient)->standardCost ?? '1';
                            $stock->record(new NewStockMovement(MovementType::Receipt, $storeId, $ingredient, bcmul($quantity, '100', 4), $cost, reason: 'opening top-up', notes: 'Demo ingredient stock'));
                        }
                    }
                }
            }

            // What the settled demo bills used (queued jobs are discarded while seeding).
            $consume = app(RecordBillConsumption::class);

            foreach ($settled as $bill) {
                $consume->handle($bill);
            }

            $kitchen = Outlet::query()->where('property_id', $propertyId)->where('code', 'MR')->first()?->store_id;

            if ($kitchen !== null && $itemId('Tomato') !== null && $menu->has('GR04')) {
                app(RecordWastage::class)->handle(new NewWastage($kitchen, WastageReason::Spoiled, 1, [['item_id' => $itemId('Tomato'), 'quantity' => '3']], [['menu_item_id' => $menu['GR04']->id, 'portions' => '2']],
                    notes: 'Cold-room failure overnight: tomatoes spoiled, two burgers dropped at the pass'));
            }
        });
    }
}
