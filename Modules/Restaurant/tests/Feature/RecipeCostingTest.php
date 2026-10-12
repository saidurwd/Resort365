<?php

/*
| Recipes, plate cost, sale consumption and wastage (Step 5.7). "Done when": selling 10 burgers takes the exact
| ingredient quantities out of the kitchen store (the ledger side is in Accounting's RecipePostingTest).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Restaurant\Actions\RecordBillConsumption;
use Modules\Restaurant\Actions\RecordVoidedLineWastage;
use Modules\Restaurant\Actions\RecordWastage;
use Modules\Restaurant\Actions\ReverseBillConsumption;
use Modules\Restaurant\Actions\SaveRecipe;
use Modules\Restaurant\DTOs\NewRecipe;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Events\RestaurantBillVoided;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Listeners\QueueIngredientConsumption;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\Recipe;
use Modules\Restaurant\Models\SaleConsumption;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Services\FoodCosting;
use Modules\Restaurant\Services\RecipeBook;

use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/recipe-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

it('sells 10 burgers and takes the exact ingredients out of the kitchen store', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    cashierOn($setup);

    $bill = rcSell($setup, $burger, 10);

    // Per portion: beef 0.2, flour 0.12, cheddar 0.03, tomato 0.05 ÷ 0.9 = 0.0556, sauce 0.03 kg = ketchup 0.006 + oil 0.009.
    expect(rcHeld($items['beef'], $store))->toBe('98.0000')->and(rcHeld($items['flour'], $store))->toBe('98.8000')->and(rcHeld($items['cheddar'], $store))->toBe('99.7000')
        ->and(rcHeld($items['tomato'], $store))->toBe('99.4440')->and(rcHeld($items['ketchup'], $store))->toBe('99.9400')->and(rcHeld($items['oil'], $store))->toBe('99.9100');

    $rows = booking(fn () => SaleConsumption::query()->where('pos_bill_id', $bill['id'])->get());
    $cost = fn (Item $item): string => (string) $rows->firstWhere('item_id', $item->id)?->total_cost;

    expect($rows)->toHaveCount(6)->and($cost($items['beef']))->toBe('1600.00')->and($cost($items['flour']))->toBe('72.00')->and($cost($items['cheddar']))->toBe('360.00')->and($cost($items['tomato']))->toBe('38.92')
        ->and($cost($items['ketchup']))->toBe('8.40')->and($cost($items['oil']))->toBe('17.10')->and($rows->every(fn (SaleConsumption $row): bool => $row->is_complimentary === false && $row->reversed_at === null))->toBeTrue()
        ->and(booking(fn () => app(RecordBillConsumption::class)->handle(PosBill::query()->findOrFail($bill['id']))))->toBe(0);
});

it('does not consume anything without a recipe, a store, or a settled bill', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $setup['items']['naan'], 'quantity' => 2])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $naanBill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');

    expect(booking(fn () => app(RecordBillConsumption::class)->handle(PosBill::query()->findOrFail($naanBill['id']))))->toBe(0);

    billApi("bills/{$naanBill['id']}/payments", ['method' => 'cash', 'amount' => $naanBill['due']])->assertOk();
    expect(booking(fn (): int => SaleConsumption::query()->count()))->toBe(0);

    booking(fn () => DB::table('outlets')->where('id', $setup['terminal']->outlet_id)->update(['store_id' => null]));
    rcSell($setup, $burger, 1, 'T2');
    expect(booking(fn (): int => SaleConsumption::query()->count()))->toBe(0);
});

it('uses a variant\'s recipe instead of the item\'s, and adds a chosen modifier\'s', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    $rice = rcItem('Rice', '80', '100', $store);
    $salt = rcItem('Salt', '40', '100', $store);
    $curry = booking(fn (): MenuItem => MenuItem::query()->findOrFail($setup['items']['curry']));
    booking(function () use ($curry, $rice, $salt, $setup): void {
        $save = app(SaveRecipe::class);
        $save->handle(new NewRecipe($curry->property_id, RecipeKind::Dish, [['item_id' => $rice->id, 'quantity' => '0.3']], menuItemId: $curry->id));
        $save->handle(new NewRecipe($curry->property_id, RecipeKind::Variant, [['item_id' => $rice->id, 'quantity' => '0.15']], menuItemId: $curry->id, variantId: $setup['variants']['Half']));
        $save->handle(new NewRecipe($curry->property_id, RecipeKind::Modifier, [['item_id' => $salt->id, 'quantity' => '0.01']], modifierId: $setup['modifiers']['mild']));
    });
    $book = booking(fn (): RecipeBook => app(RecipeBook::class));

    expect(booking(fn (): array => $book->portion($curry, $setup['variants']['Half'])))->toBe([$rice->id => '0.1500'])
        ->and(booking(fn (): array => $book->portion($curry, $setup['variants']['Full'])))->toBe([$rice->id => '0.3000'])
        ->and(booking(fn (): array => $book->portion($curry, $setup['variants']['Full'], [$setup['modifiers']['mild']])))->toBe([$rice->id => '0.3000', $salt->id => '0.0100']);

    cashierOn($setup);
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $curry->id, 'variant_id' => $setup['variants']['Full'], 'modifier_ids' => [$setup['modifiers']['mild']], 'quantity' => 2])->assertOk()->json('order.lines.0.id');
    expect(storedLine($line)->modifier_ids)->toBe([$setup['modifiers']['mild']]);

    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    billApi("bills/{$bill['id']}/payments", ['method' => 'cash', 'amount' => $bill['due']])->assertOk();

    expect(rcHeld($rice, $store))->toBe('99.4000')->and(rcHeld($salt, $store))->toBe('99.9800');
});

it('takes a direct-stock item and a combo\'s components out of stock', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    $cola = rcItem('Cola', '35', '100', $store);
    $flour = rcItem('Flour', '60', '100', $store);
    [$soda, $combo, $naan] = booking(function () use ($setup, $cola, $flour): array {
        $soda = MenuItem::factory()->create(['code' => 'COLA', 'name' => ['en' => 'Cola'], 'course' => 'drink', 'kind' => 'direct_stock', 'inventory_item_id' => $cola->id]);
        OutletMenuItem::factory()->create(['outlet_id' => $setup['terminal']->outlet_id, 'menu_item_id' => $soda->id, 'price' => '60.00']);
        $naan = MenuItem::query()->findOrFail($setup['items']['naan']);
        app(SaveRecipe::class)->handle(new NewRecipe($naan->property_id, RecipeKind::Dish, [['item_id' => $flour->id, 'quantity' => '0.1']], menuItemId: $naan->id));
        $combo = MenuItem::factory()->create(['code' => 'SET', 'name' => ['en' => 'Set'], 'course' => 'main', 'kind' => 'combo']);
        OutletMenuItem::factory()->create(['outlet_id' => $setup['terminal']->outlet_id, 'menu_item_id' => $combo->id, 'price' => '150.00']);
        DB::table('combo_components')->insert([
            ['tenant_id' => $combo->tenant_id, 'property_id' => $combo->property_id, 'menu_item_id' => $combo->id, 'component_item_id' => $naan->id, 'component_variant_id' => null, 'quantity' => 2, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $combo->tenant_id, 'property_id' => $combo->property_id, 'menu_item_id' => $combo->id, 'component_item_id' => $soda->id, 'component_variant_id' => null, 'quantity' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return [$soda, $combo, $naan];
    });
    cashierOn($setup);

    rcSell($setup, $soda, 3);
    expect(rcHeld($cola, $store))->toBe('97.0000');

    rcSell($setup, $combo, 2, 'T2');
    expect(rcHeld($flour, $store))->toBe('99.6000')->and(rcHeld($cola, $store))->toBe('95.0000')->and($naan->id)->not->toBe($combo->id);
});

it('lets a sale through when the store runs short, at the cost it has', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    cashierOn($setup);
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Issue, $store->id, $items['beef']->id, '99.5')));

    $bill = rcSell($setup, $burger, 10);

    expect(rcHeld($items['beef'], $store))->toBe('-1.5000')->and(booking(fn () => SaleConsumption::query()->where('pos_bill_id', $bill['id'])->where('item_id', $items['beef']->id)->value('unit_cost')))->toBe('800.0000');
});

it('flags a complimentary bill\'s consumption', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    cashierOn($setup);
    $order = openTable($setup['tables']['T1']);
    orderApi('POST', $order, '/lines', ['item_id' => $burger->id, 'quantity' => 1])->assertOk();
    orderApi('POST', $order, '/send')->assertOk();
    $bill = billApi("orders/{$order->id}/bill/print", ['mode' => 'none'])->assertOk()->json('billing.bills.0');
    booking(fn () => PosBill::query()->whereKey($bill['id'])->update(['is_complimentary' => true, 'status' => 'settled']));
    booking(fn () => app(RecordBillConsumption::class)->handle(PosBill::query()->findOrFail($bill['id'])));

    expect(booking(fn () => SaleConsumption::query()->where('pos_bill_id', $bill['id'])->pluck('is_complimentary')->unique()->all()))->toBe([true]);
});

it('puts a voided bill\'s ingredients back unless the food was made', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    cashierOn($setup);
    $bill = rcSell($setup, $burger, 10);
    $event = fn (bool $prepared): RestaurantBillVoided => new RestaurantBillVoided(tenant('sunrise')->id, bookingIds()['property'], $setup['terminal']->outlet_id, $bill['id'], 1, now()->toDateString(), '4500.00', $prepared, 'Wrong table');

    booking(fn () => app(QueueIngredientConsumption::class)->onVoided($event(true)));
    expect(rcHeld($items['beef'], $store))->toBe('98.0000');

    booking(fn () => app(QueueIngredientConsumption::class)->onVoided($event(false)));
    $rows = booking(fn () => SaleConsumption::query()->where('pos_bill_id', $bill['id'])->get());

    expect(rcHeld($items['beef'], $store))->toBe('100.0000')->and(rcHeld($items['tomato'], $store))->toBe('100.0000')->and($rows->every(fn (SaleConsumption $row): bool => $row->reversed_at !== null && $row->reversal_movement_id !== null))->toBeTrue()
        ->and(booking(fn () => app(ReverseBillConsumption::class)->handle(PosBill::query()->findOrFail($bill['id']))))->toBe(0);
});

it('refuses recipes that cannot work', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    $rice = rcItem('Rice', '80', '10', $store);
    $curry = booking(fn (): MenuItem => MenuItem::query()->findOrFail($setup['items']['curry']));
    $save = fn (NewRecipe $recipe) => booking(fn () => app(SaveRecipe::class)->handle($recipe));
    $dish = fn (array $lines) => new NewRecipe($curry->property_id, RecipeKind::Dish, $lines, menuItemId: $curry->id);
    $service = booking(function (): Item {
        $item = Item::query()->first() ?? rcItem('Seed');

        return app(SaveItem::class)->handle(null, ['item_category_id' => $item->item_category_id, 'name' => 'Cleaning', 'base_unit_id' => $item->base_unit_id, 'is_stockable' => false]);
    });

    expect(fn () => $save($dish([['item_id' => $rice->id, 'quantity' => '0']])))->toThrow(RestaurantSetupInvalid::class, 'quantity above zero')
        ->and(fn () => $save($dish([['item_id' => $rice->id, 'quantity' => '']])))->toThrow(RestaurantSetupInvalid::class, 'quantity above zero')
        ->and(fn () => $save($dish([['item_id' => $rice->id, 'quantity' => '1', 'waste_percent' => '100']])))->toThrow(RestaurantSetupInvalid::class, 'waste below 100%')
        ->and(fn () => $save($dish([['item_id' => $service->id, 'quantity' => '1']])))->toThrow(RestaurantSetupInvalid::class, 'not a stocked item')
        ->and(fn () => $save($dish([['item_id' => $rice->id, 'sub_recipe_id' => 1, 'quantity' => '1']])))->toThrow(RestaurantSetupInvalid::class, 'not both')
        ->and(fn () => $save($dish([['item_id' => 999999, 'quantity' => '1']])))->toThrow(RestaurantSetupInvalid::class, 'Unknown ingredient')
        ->and(fn () => $save(new NewRecipe($curry->property_id, RecipeKind::Sub, [['item_id' => $rice->id, 'quantity' => '1']], name: ' ')))->toThrow(RestaurantSetupInvalid::class, 'needs a name');
});

it('refuses a sub-recipe inside itself, and deleting one that is in use', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    $rice = rcItem('Rice', '80', '10', $store);
    $property = bookingIds()['property'];
    $save = fn (NewRecipe $recipe) => booking(fn () => app(SaveRecipe::class)->handle($recipe));
    $stock = $save(new NewRecipe($property, RecipeKind::Sub, [['item_id' => $rice->id, 'quantity' => '1']], name: 'Stock', yieldQuantity: '2', yieldUnit: 'l'));
    $sauce = $save(new NewRecipe($property, RecipeKind::Sub, [['sub_recipe_id' => $stock?->id, 'quantity' => '1']], name: 'Sauce', yieldQuantity: '1', yieldUnit: 'kg'));

    expect(fn () => $save(new NewRecipe($property, RecipeKind::Sub, [['sub_recipe_id' => $sauce?->id, 'quantity' => '1']], recipeId: $stock?->id, name: 'Stock', yieldQuantity: '2', yieldUnit: 'l')))->toThrow(RestaurantSetupInvalid::class, 'contain itself')
        ->and(fn () => $save(new NewRecipe($property, RecipeKind::Sub, [], recipeId: $stock?->id, name: 'Stock')))->toThrow(RestaurantSetupInvalid::class, 'used in other recipes');

    $save(new NewRecipe($property, RecipeKind::Sub, [], recipeId: $sauce?->id, name: 'Sauce'));
    $save(new NewRecipe($property, RecipeKind::Sub, [], recipeId: $stock?->id, name: 'Stock'));
    expect(booking(fn (): int => Recipe::query()->count()))->toBe(0);
});

it('costs a plate at the store\'s average cost and shows the food cost percent', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);

    $rows = booking(fn (): array => app(FoodCosting::class)->rows(bookingIds()['property']));
    $row = collect($rows)->first(fn (array $candidate): bool => $candidate['item']->code === 'BURGER');
    $noRecipe = collect($rows)->first(fn (array $candidate): bool => $candidate['item']->code === 'NAAN');

    // 0.2 × 800 + 0.12 × 60 + 0.03 × 1200 + 0.0556 × 70 + 0.006 × 140 + 0.009 × 190 = 209.64
    expect($row['cost'])->toBe('209.64')->and($row['percent'])->toBe('46.59')->and($row['suggested'])->toBe('698.80')->and($row['price'])->toBe('450.00')->and($noRecipe['has_recipe'])->toBeFalse();

    // A dearer delivery changes the average, and the plate cost follows.
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $items['beef']->id, '100', '1000')));
    $dearer = collect(booking(fn (): array => app(FoodCosting::class)->rows(bookingIds()['property'])))->first(fn (array $candidate): bool => $candidate['item']->code === 'BURGER');
    expect($dearer['cost'])->toBe('229.64');
});

it('writes off ingredients and dishes at cost', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    $record = fn (NewWastage $wastage) => booking(fn (): WastageEntry => app(RecordWastage::class)->handle($wastage));

    $entry = $record(new NewWastage($store->id, WastageReason::Spoiled, 1, [['item_id' => $items['tomato']->id, 'quantity' => '3']], [['menu_item_id' => $burger->id, 'portions' => '2']], notes: 'Cold room failed'));

    expect($entry->wastage_no)->toStartWith('WST-')->and($entry->lines)->toHaveCount(6)->and(rcHeld($items['tomato'], $store))->toBe('96.8888')->and(rcHeld($items['beef'], $store))->toBe('99.6000')
        ->and($entry->total_cost)->toBe('629.28')
        ->and(fn () => $record(new NewWastage($store->id, WastageReason::Other, 1)))->toThrow(RestaurantSetupInvalid::class, 'Enter what was wasted')
        ->and(fn () => $record(new NewWastage($store->id, WastageReason::Other, 1, [['item_id' => $items['beef']->id, 'quantity' => '500']])))->toThrow(RestaurantSetupInvalid::class, 'holds only')
        ->and(fn () => $record(new NewWastage(999999, WastageReason::Other, 1, [['item_id' => $items['beef']->id, 'quantity' => '1']])))->toThrow(RestaurantSetupInvalid::class, 'Choose the store');

    $plain = booking(fn (): MenuItem => MenuItem::query()->findOrFail($setup['items']['naan']));
    expect(fn () => $record(new NewWastage($store->id, WastageReason::Dropped, 1, dishes: [['menu_item_id' => $plain->id, 'portions' => '1']])))->toThrow(RestaurantSetupInvalid::class, 'no recipe');
});

it('writes off a line voided after it was made, once', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    $manager = posStaff(DefaultRole::FnbManager, '9999', $setup['terminal']);
    onDevice($setup['terminal']);
    pinSignIn($manager, '9999')->assertRedirect();
    openSession($setup['terminal'], $manager->id);
    $order = openTable($setup['tables']['T1']);
    $line = orderApi('POST', $order, '/lines', ['item_id' => $burger->id, 'quantity' => 2])->assertOk()->json('order.lines.0.id');
    orderApi('POST', $order, '/send')->assertOk();
    orderApi('POST', $order, "/lines/{$line}/void", ['reason' => 'quality_issue', 'note' => 'Dropped at the pass', 'wastage' => true])->assertOk();

    $entries = booking(fn () => WastageEntry::query()->where('source_type', 'pos_order_line')->get());

    expect($entries)->toHaveCount(1)->and($entries[0]->source_id)->toBe($line)->and(rcHeld($items['beef'], $store))->toBe('99.6000')
        ->and(booking(fn () => app(RecordVoidedLineWastage::class)->handle(storedLine($line))))->toBeNull();
});

it('back-fills the consumption of settled bills with the command', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    cashierOn($setup);
    $bill = rcSell($setup, $burger, 3);
    booking(fn () => DB::table('sale_consumptions')->delete());
    booking(fn () => DB::table('stock_movements')->where('source_type', 'pos_bill')->delete());

    artisan('restaurant:consume-sales', ['--tenant' => 'sunrise'])->assertSuccessful();

    expect(booking(fn (): int => SaleConsumption::query()->where('pos_bill_id', $bill['id'])->count()))->toBe(6);
});

it('writes recipes, sub-recipes, costing and wastage through the screens', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger, $items] = rcBurger($setup, $store);
    staffUser(DefaultRole::FnbManager);

    get(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"))->assertOk()->assertSeeHtml('data-recipe-form')->assertSeeHtml('data-plate="Main Restaurant"')->assertSee('209.64');
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"), ['sections' => ['dish' => [['item_id' => $items['beef']->id, 'quantity' => '0.25', 'waste_percent' => '']]]])->assertSessionHas('success');
    expect(booking(fn () => Recipe::query()->where('menu_item_id', $burger->id)->sole()->lines->sole()->quantity))->toBe('0.2500');
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"), ['sections' => ['dish' => [['item_id' => $items['beef']->id, 'quantity' => '0.25', 'waste_percent' => '100']]]])->assertSessionHasErrors('sections.dish.0.waste_percent');
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"), ['sections' => ['dish' => [['item_id' => $items['beef']->id, 'quantity' => '']]]])->assertSessionHas('error');

    get(tenantUrl('sunrise', '/restaurant/costing'))->assertOk()->assertSeeHtml('data-dish="BURGER"')->assertSeeHtml('data-percent');
    get(tenantUrl('sunrise', '/restaurant/costing?outlet='.$setup['terminal']->outlet_id))->assertOk()->assertSeeHtml('data-dish="BURGER"');

    get(tenantUrl('sunrise', '/restaurant/sub-recipes/new'))->assertOk()->assertSeeHtml('data-sub-recipe-form');
    post(tenantUrl('sunrise', '/restaurant/sub-recipes'), ['name' => '', 'yield_quantity' => '1', 'lines' => [['item_id' => $items['oil']->id, 'quantity' => '1']]])->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/restaurant/sub-recipes'), ['name' => 'Garlic oil', 'yield_quantity' => '2', 'yield_unit' => 'l', 'lines' => [['item_id' => $items['oil']->id, 'quantity' => '1.5']]])->assertSessionHas('success');
    $sub = booking(fn (): Recipe => Recipe::query()->where('kind', 'sub')->where('name', 'Garlic oil')->sole());
    get(tenantUrl('sunrise', '/restaurant/sub-recipes'))->assertOk()->assertSeeHtml('data-sub-recipe="Garlic oil"');
    get(tenantUrl('sunrise', "/restaurant/sub-recipes/{$sub->id}/edit"))->assertOk();
    put(tenantUrl('sunrise', "/restaurant/sub-recipes/{$sub->id}"), ['name' => 'Garlic oil', 'yield_quantity' => '3', 'lines' => [['item_id' => $items['oil']->id, 'quantity' => '2']]])->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/restaurant/sub-recipes/{$sub->id}"))->assertRedirect();
    expect(booking(fn (): bool => Recipe::query()->where('name', 'Garlic oil')->exists()))->toBeFalse();

    get(tenantUrl('sunrise', '/restaurant/wastage/new'))->assertOk()->assertSeeHtml('data-wastage-form');
    post(tenantUrl('sunrise', '/restaurant/wastage'), ['store_id' => $store->id, 'reason' => 'bogus'])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', '/restaurant/wastage'), ['store_id' => $store->id, 'reason' => 'expired', 'items' => [['item_id' => $items['tomato']->id, 'quantity' => '2']]])->assertSessionHas('success');
    $entry = booking(fn (): WastageEntry => WastageEntry::query()->sole());
    get(tenantUrl('sunrise', '/restaurant/wastage'))->assertOk()->assertSeeHtml('data-entry="'.$entry->wastage_no.'"');
    get(tenantUrl('sunrise', "/restaurant/wastage/{$entry->id}"))->assertOk()->assertSee('140.00');
});

it('links a bottled drink to its inventory item on the menu item form', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    $cola = rcItem('Cola', '35', '10', $store);
    $soda = booking(fn (): MenuItem => MenuItem::factory()->create(['code' => 'COLA', 'name' => ['en' => 'Cola'], 'course' => 'drink', 'kind' => 'direct_stock']));
    staffUser(DefaultRole::FnbManager);
    $payload = ['menu_category_id' => $soda->menu_category_id, 'code' => 'COLA', 'name' => ['en' => 'Cola'], 'course' => 'drink', 'kind' => 'direct_stock', 'inventory_item_id' => $cola->id, 'sort_order' => 0];

    get(tenantUrl('sunrise', "/restaurant/menu/items/{$soda->id}"))->assertOk()->assertSee('Inventory item')->assertSeeHtml('data-recipe-link');
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$soda->id}"), $payload)->assertSessionHas('success');
    expect(booking(fn () => MenuItem::query()->findOrFail($soda->id)->inventory_item_id))->toBe($cola->id);

    put(tenantUrl('sunrise', "/restaurant/menu/items/{$soda->id}"), [...$payload, 'kind' => 'dish'])->assertSessionHas('success');
    expect(booking(fn () => MenuItem::query()->findOrFail($soda->id)->inventory_item_id))->toBeNull();
});

it('keeps recipes and wastage to the people who hold the permissions', function (): void {
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);

    staffUser(DefaultRole::OutletCashier);
    foreach (["/restaurant/menu/items/{$burger->id}/recipe", '/restaurant/costing', '/restaurant/sub-recipes', '/restaurant/wastage', '/restaurant/wastage/new'] as $url) {
        get(tenantUrl('sunrise', $url))->assertForbidden();
    }
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"), ['sections' => []])->assertForbidden();
    post(tenantUrl('sunrise', '/restaurant/wastage'), [])->assertForbidden();

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/restaurant/costing'))->assertOk();
    get(tenantUrl('sunrise', '/restaurant/wastage'))->assertOk()->assertDontSeeHtml('data-new-wastage');
    get(tenantUrl('sunrise', '/restaurant/wastage/new'))->assertForbidden();
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$burger->id}/recipe"), ['sections' => []])->assertForbidden();
});
