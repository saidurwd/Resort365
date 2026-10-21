<?php

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Restaurant\Actions\SaveRecipe;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\OutletMenuItem;

require_once __DIR__.'/pos-setup.php';

function rcItem(string $name, string $cost = '100', string $quantity = '100', ?Store $store = null): Item
{
    return booking(function () use ($name, $cost, $quantity, $store): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => 'Food'], ['sort_order' => 0, 'is_active' => true, 'account_class' => 'food']);
        $item = Item::query()->where('name', $name)->first() ?? app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id'), 'standard_cost' => $cost]);

        if ($store instanceof Store && (float) $quantity > 0) {
            app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $quantity, $cost));
        }

        return $item;
    });
}

function rcHeld(Item $item, Store $store): string
{
    return booking(fn (): string => app(StockMovementContract::class)->balance($store->id, $item->id)->quantity);
}

/**
 * The Main Restaurant's kitchen store, linked to the outlet.
 *
 * @param  array<string, mixed>  $setup
 */
function rcKitchen(array $setup): Store
{
    return booking(function () use ($setup): Store {
        $store = app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => 'KIT', 'name' => 'Kitchen store', 'kind' => 'kitchen']);
        DB::table('outlets')->where('id', $setup['terminal']->outlet_id)->update(['store_id' => $store->id]);

        return $store;
    });
}

/**
 * A beef burger at 450.00 whose recipe is: beef 0.2, flour 0.12, cheddar 0.03, tomato 0.05 (10% waste) and 0.03 kg of
 * burger sauce (1 kg = ketchup 0.2 + oil 0.3). Returns the dish and the ingredients by key.
 *
 * @param  array<string, mixed>  $setup
 * @return array{0: MenuItem, 1: array<string, Item>}
 */
function rcBurger(array $setup, Store $store): array
{
    $items = [
        'beef' => rcItem('Beef', '800', '100', $store), 'flour' => rcItem('Flour', '60', '100', $store), 'cheddar' => rcItem('Cheddar', '1200', '100', $store),
        'tomato' => rcItem('Tomato', '70', '100', $store), 'ketchup' => rcItem('Ketchup', '140', '100', $store), 'oil' => rcItem('Oil', '190', '100', $store),
    ];

    $burger = booking(function () use ($setup, $items): MenuItem {
        $burger = MenuItem::factory()->create(['code' => 'BURGER', 'name' => ['en' => 'Beef burger'], 'course' => 'main']);
        OutletMenuItem::factory()->create(['outlet_id' => $setup['terminal']->outlet_id, 'menu_item_id' => $burger->id, 'price' => '450.00', 'kitchen_station_id' => $setup['stations']['Hot kitchen']]);
        $save = app(SaveRecipe::class);
        $sauce = $save->handle(new NewRecipe(bookingIds()['property'], RecipeKind::Sub, [['item_id' => $items['ketchup']->id, 'quantity' => '0.2'], ['item_id' => $items['oil']->id, 'quantity' => '0.3']], name: 'Burger sauce', yieldQuantity: '1', yieldUnit: 'kg'));
        $save->handle(new NewRecipe(bookingIds()['property'], RecipeKind::Dish, [
            ['item_id' => $items['beef']->id, 'quantity' => '0.2'], ['item_id' => $items['flour']->id, 'quantity' => '0.12'], ['item_id' => $items['cheddar']->id, 'quantity' => '0.03'],
            ['item_id' => $items['tomato']->id, 'quantity' => '0.05', 'waste_percent' => '10'], ['sub_recipe_id' => $sauce?->id, 'quantity' => '0.03'],
        ], menuItemId: $burger->id));

        return $burger;
    });

    return [$burger, $items];
}

/**
 * Sells $quantity of a dish at T1 and settles the bill in cash.
 *
 * @param  array<string, mixed>  $setup
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function rcSell(array $setup, MenuItem $dish, int $quantity, string $table = 'T1', array $extra = []): array
{
    $order = openTable($setup['tables'][$table]);
    orderApi('POST', $order, '/lines', ['item_id' => $dish->id, 'quantity' => $quantity, ...$extra])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    billApi("bills/{$bill['id']}/payments", ['method' => 'cash', 'amount' => $bill['due']])->assertOk();

    return $bill;
}

/**
 * A dish at the Main Restaurant with a one-ingredient recipe: `$quantity` kg of an ingredient bought at `$unitCost` a kg, so a
 * portion costs quantity × unit cost.
 *
 * @param  array<string, mixed>  $setup
 */
function rcDish(array $setup, Store $store, string $code, string $name, string $price, string $ingredient, string $unitCost, string $quantity): MenuItem
{
    $item = rcItem($ingredient, $unitCost, '1000', $store);

    return booking(function () use ($setup, $code, $name, $price, $item, $quantity): MenuItem {
        $dish = MenuItem::factory()->create(['code' => $code, 'name' => ['en' => $name], 'course' => 'main']);
        OutletMenuItem::factory()->create(['outlet_id' => $setup['terminal']->outlet_id, 'menu_item_id' => $dish->id, 'price' => $price, 'kitchen_station_id' => $setup['stations']['Hot kitchen']]);
        app(SaveRecipe::class)->handle(new NewRecipe(bookingIds()['property'], RecipeKind::Dish, [['item_id' => $item->id, 'quantity' => $quantity]], menuItemId: $dish->id));

        return $dish;
    });
}
