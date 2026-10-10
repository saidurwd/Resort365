<?php

/*
| Menu management (Step 3.2). "Done when": a realistic demo menu (≈60 items, with variants and
| modifiers) is priced differently in two outlets (see DemoSeederTest); here the screens and rules.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

/**
 * Two outlets (MR with a Hot kitchen and a Bar, PB with a Bar) and a Mains category.
 *
 * @return array{mr: Outlet, pb: Outlet, mains: MenuCategory}
 */
function menuSetup(): array
{
    return booking(function (): array {
        $mr = Outlet::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'MR', 'name' => 'Main Restaurant']);
        $pb = Outlet::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'PB', 'name' => 'Pool Bar', 'type' => 'bar']);
        KitchenStation::factory()->create(['outlet_id' => $mr->id, 'name' => 'Hot kitchen']);
        KitchenStation::factory()->create(['outlet_id' => $mr->id, 'name' => 'Bar']);
        KitchenStation::factory()->create(['outlet_id' => $pb->id, 'name' => 'Bar']);

        return ['mr' => $mr, 'pb' => $pb, 'mains' => MenuCategory::factory()->create(['property_id' => bookingIds()['property'], 'name' => ['en' => 'Mains']])];
    });
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function itemForm(int $categoryId, array $overrides = []): array
{
    return [...['menu_category_id' => $categoryId, 'code' => 'BD01', 'name' => ['en' => 'Chicken curry', 'bn' => 'মুরগির তরকারি'], 'course' => 'main', 'kind' => 'dish',
        'dietary_tags' => ['halal', 'spicy'], 'allergens' => ['milk'], 'variants' => ['Half', 'Full'], 'is_active' => '1'], ...$overrides];
}

/**
 * item id, variant id, price, station id, on sale (not 86) of each row the outlet sells.
 *
 * @return list<array{int, int|null, string, int|null, bool}>
 */
function priceRows(Outlet $outlet): array
{
    return booking(fn (): array => OutletMenuItem::query()->where('outlet_id', $outlet->id)->orderBy('id')->get()
        ->map(fn (OutletMenuItem $row): array => [$row->menu_item_id, $row->menu_item_variant_id, $row->price, $row->kitchen_station_id, $row->is_available])->values()->all());
}

it('creates an item with names in two languages, variants and modifier groups', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/modifiers'), ['name' => 'Spice level', 'min_select' => 1, 'max_select' => 1,
        'modifiers' => [['name' => 'Mild'], ['name' => 'Hot', 'price_delta' => '0']]])->assertSessionHas('success');
    $group = booking(fn (): ModifierGroup => ModifierGroup::query()->sole());

    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['modifier_group_ids' => [$group->id]]))->assertSessionHas('success');
    $item = booking(fn (): MenuItem => MenuItem::query()->with(['variants', 'modifierGroups'])->sole());

    expect([$item->code, $item->name, $item->translated('name', 'bn'), $item->translated('name', 'fr'), $item->dietary_tags])
        ->toBe(['BD01', ['en' => 'Chicken curry', 'bn' => 'মুরগির তরকারি'], 'মুরগির তরকারি', 'Chicken curry', ['halal', 'spicy']])
        ->and($item->variants->pluck('name')->all())->toBe(['Half', 'Full'])
        ->and($item->modifierGroups->pluck('name')->all())->toBe(['Spice level']);

    get(tenantUrl('sunrise', "/restaurant/menu/items/{$item->id}"))->assertOk()->assertSeeHtml('মুরগির তরকারি');
    getJson(tenantUrl('sunrise', '/restaurant/menu/items/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 1);

    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'bd01']))->assertSessionHasErrors('code');
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'X1', 'course' => 'brunch', 'allergens' => ['dust']]))->assertSessionHasErrors(['course', 'allergens.0']);
    post(tenantUrl('sunrise', '/restaurant/menu/modifiers'), ['name' => 'Sauce', 'min_select' => 2, 'max_select' => 1, 'modifiers' => [['name' => 'BBQ']]])->assertSessionHas('error');
});

it('keeps the prices of variants still there when the variants change', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mr' => $mr, 'mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id));
    $item = booking(fn (): MenuItem => MenuItem::query()->with('variants')->sole());
    [$half, $full] = $item->variants->all();

    put(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"), ['rows_json' => json_encode([
        ['item_id' => $item->id, 'variant_id' => $half->id, 'on_sale' => true, 'price' => '380'],
        ['item_id' => $item->id, 'variant_id' => $full->id, 'on_sale' => true, 'price' => '650'],
    ])])->assertSessionHas('success');

    put(tenantUrl('sunrise', "/restaurant/menu/items/{$item->id}"), itemForm($mains->id, ['variants' => ['Full', 'Family']]))->assertSessionHas('success');

    expect(booking(fn () => MenuItem::query()->with('variants')->sole()->variants->pluck('name')->all()))->toBe(['Full', 'Family'])
        ->and(priceRows($mr))->toBe([[$item->id, $full->id, '650.00', null, true]]);

    // No variants any more: the per-variant prices go, the item is priced on its own.
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$item->id}"), itemForm($mains->id, ['variants' => []]))->assertSessionHas('success');
    expect(priceRows($mr))->toBe([]);
});

it('builds combos from other items, never from combos', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['variants' => []]));
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'PR05', 'name' => ['en' => 'Plain rice'], 'variants' => []]));
    [$curry, $rice] = booking(fn () => MenuItem::query()->orderBy('id')->get()->all());

    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'CB01', 'name' => ['en' => 'Set lunch'], 'kind' => 'combo', 'variants' => []]))->assertSessionHas('error');
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'CB01', 'name' => ['en' => 'Set lunch'], 'kind' => 'combo', 'variants' => [],
        'components' => [['item_id' => $curry->id, 'quantity' => 1], ['item_id' => $rice->id, 'quantity' => 2]]]))->assertSessionHas('success');
    $combo = booking(fn (): MenuItem => MenuItem::query()->where('code', 'CB01')->with('components')->sole());
    expect($combo->components->map(fn ($component): array => [$component->component_item_id, $component->quantity])->all())->toBe([[$curry->id, 1], [$rice->id, 2]]);

    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['code' => 'CB02', 'name' => ['en' => 'Double set'], 'kind' => 'combo', 'variants' => [],
        'components' => [['item_id' => $combo->id]]]))->assertSessionHas('error');
});

it('prices each outlet on its own, with its stations, schedules and 86', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mr' => $mr, 'pb' => $pb, 'mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['variants' => []]));
    $item = booking(fn (): MenuItem => MenuItem::query()->sole());
    [$hot, $mrBar, $pbBar] = booking(fn () => KitchenStation::query()->orderBy('id')->get()->all());

    post(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/schedules"), ['name' => 'Lunch', 'days_of_week' => ['mon', 'fri'], 'start_time' => '12:00', 'end_time' => '15:00'])->assertSessionHas('success');
    $lunch = booking(fn (): MenuSchedule => MenuSchedule::query()->sole());

    get(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"))->assertOk()->assertSee('BD01');
    put(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"), ['rows_json' => json_encode([
        ['item_id' => $item->id, 'variant_id' => null, 'on_sale' => true, 'price' => '650', 'station_id' => $hot->id, 'schedule_ids' => [$lunch->id], 'is_package_eligible' => true],
    ])])->assertSessionHas('success');

    // Another outlet's station or schedule is refused.
    put(tenantUrl('sunrise', "/restaurant/outlets/{$pb->id}/prices"), ['rows_json' => json_encode([
        ['item_id' => $item->id, 'variant_id' => null, 'on_sale' => true, 'price' => '700', 'station_id' => $hot->id],
    ])])->assertSessionHas('error');
    put(tenantUrl('sunrise', "/restaurant/outlets/{$pb->id}/prices"), ['rows_json' => json_encode([
        ['item_id' => $item->id, 'variant_id' => null, 'on_sale' => true, 'price' => '715', 'station_id' => $pbBar->id],
    ])])->assertSessionHas('success');
    put(tenantUrl('sunrise', "/restaurant/outlets/{$pb->id}/prices"), ['rows_json' => json_encode([['item_id' => $item->id, 'on_sale' => true]])])->assertSessionHasErrors('rows.0.price');

    expect(priceRows($mr))->toBe([[$item->id, null, '650.00', $hot->id, true]])
        ->and(priceRows($pb))->toBe([[$item->id, null, '715.00', $pbBar->id, true]]);

    // The chef marks it sold out at the Main Restaurant only; cannot change prices.
    $row = booking(fn (): OutletMenuItem => OutletMenuItem::query()->where('outlet_id', $mr->id)->sole());
    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"))->assertOk()->assertSeeHtml('data-sold-out');
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices/{$row->id}/sold-out"), ['sold_out' => true])->assertOk()->assertJson(['sold_out' => true]);
    put(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"), ['rows_json' => json_encode([])])->assertForbidden();
    [[, , , , $mrOnSale]] = priceRows($mr);
    [[, , , , $pbOnSale]] = priceRows($pb);
    expect($mrOnSale)->toBeFalse()->and($pbOnSale)->toBeTrue();

    // A row of another outlet is not found through this outlet.
    $pbRow = booking(fn (): OutletMenuItem => OutletMenuItem::query()->where('outlet_id', $pb->id)->sole());
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices/{$pbRow->id}/sold-out"), ['sold_out' => true])->assertNotFound();

    staffUser(DefaultRole::Waiter);
    postJson(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices/{$row->id}/sold-out"), ['sold_out' => false])->assertForbidden();
});

it('copies another outlet\'s prices with a percentage, matching stations by name', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mr' => $mr, 'pb' => $pb, 'mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id));
    $item = booking(fn (): MenuItem => MenuItem::query()->with('variants')->sole());
    [, $mrBar, $pbBar] = booking(fn () => KitchenStation::query()->orderBy('id')->get()->all());
    put(tenantUrl('sunrise', "/restaurant/outlets/{$mr->id}/prices"), ['rows_json' => json_encode([
        ['item_id' => $item->id, 'variant_id' => $item->variants[0]->id, 'on_sale' => true, 'price' => '380', 'station_id' => $mrBar->id],
        ['item_id' => $item->id, 'variant_id' => $item->variants[1]->id, 'on_sale' => true, 'price' => '650'],
    ])]);

    post(tenantUrl('sunrise', "/restaurant/outlets/{$pb->id}/prices/copy"), ['from_outlet_id' => $mr->id, 'percent' => '10', 'round_whole' => '1'])->assertSessionHas('success');

    expect(priceRows($pb))->toBe([[$item->id, $item->variants[0]->id, '418.00', $pbBar->id, true], [$item->id, $item->variants[1]->id, '715.00', null, true]]);
    post(tenantUrl('sunrise', "/restaurant/outlets/{$pb->id}/prices/copy"), ['from_outlet_id' => $pb->id])->assertSessionHas('error');
});

it('imports a CSV all or nothing, matching items by code', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mr' => $mr, 'pb' => $pb] = menuSetup();
    $file = fn (string $contents): UploadedFile => UploadedFile::fake()->createWithContent('menu.csv', $contents);

    post(tenantUrl('sunrise', '/restaurant/menu/import'), ['file' => $file("code,name,category,course,variants,price:MR,price:PB\n"
        ."BD01,Chicken curry,Food > Bangladeshi,main,Half|Full,380|650,420|715\nFJ04,Lemon mint,Drinks,drink,,180,198\nXX,Broken,Food,brunch,,1,\n")])
        ->assertSessionHas('import_errors', ['Line 4: course "brunch" is not one of starter, main, side, dessert, drink.']);
    expect(booking(fn (): int => MenuItem::query()->count()))->toBe(0);

    post(tenantUrl('sunrise', '/restaurant/menu/import'), ['file' => $file("code,name,category,course,variants,tax_category,price:MR,price:PB\n"
        ."BD01,Chicken curry,Food > Bangladeshi,main,Half|Full,ROOM,380|650,420|715\nFJ04,Lemon mint,Drinks,drink,,,180,198\n")])->assertSessionHas('success');

    $curry = booking(fn (): MenuItem => MenuItem::query()->where('code', 'BD01')->with(['category.parent', 'variants'])->sole());
    expect([$curry->category?->translated('name'), $curry->category?->parent?->translated('name'), $curry->variants->pluck('name')->all(), $curry->tax_category_id !== null])
        ->toBe(['Bangladeshi', 'Food', ['Half', 'Full'], true])
        ->and(collect(priceRows($pb))->pluck(2)->all())->toBe(['420.00', '715.00', '198.00']);

    post(tenantUrl('sunrise', '/restaurant/menu/import'), ['file' => $file("code,name,category,course,price:MR\nFJ04,Fresh lemon mint,Drinks,drink,200\n")])->assertSessionHas('success');
    expect(booking(fn () => MenuItem::query()->where('code', 'FJ04')->sole()->translated('name')))->toBe('Fresh lemon mint')
        ->and(booking(fn (): int => MenuCategory::query()->count()))->toBe(4)
        ->and(collect(priceRows($mr))->pluck(2)->all())->toBe(['380.00', '650.00', '200.00']);

    get(tenantUrl('sunrise', '/restaurant/menu/import/template'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertSee('price:MR');
});

it('saves whether a category\'s sales count as food or beverage', function (): void {
    staffUser(DefaultRole::FnbManager);

    post(tenantUrl('sunrise', '/restaurant/menu/categories'), ['name' => ['en' => 'Cocktails'], 'colour' => 'info', 'revenue_class' => 'beverage'])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/restaurant/menu/categories'), ['name' => ['en' => 'Starters'], 'colour' => 'primary', 'revenue_class' => ''])->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/restaurant/menu/categories'), ['name' => ['en' => 'Odd'], 'colour' => 'primary', 'revenue_class' => 'dessert'])->assertSessionHasErrors('revenue_class');

    expect(booking(fn (): array => MenuCategory::query()->orderBy('id')->get()->map(fn (MenuCategory $category): ?string => $category->revenue_class?->value)->all()))->toBe(['beverage', null]);
    get(tenantUrl('sunrise', '/restaurant/menu/categories'))->assertOk()->assertSee('Sales count as');
});

it('lets the menu be seen but not changed without restaurant.menu.manage', function (): void {
    staffUser(DefaultRole::FnbManager);
    ['mains' => $mains] = menuSetup();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id));
    $item = booking(fn (): MenuItem => MenuItem::query()->sole());

    staffUser(DefaultRole::Chef);
    get(tenantUrl('sunrise', '/restaurant/menu/items'))->assertOk();
    get(tenantUrl('sunrise', "/restaurant/menu/items/{$item->id}"))->assertOk()->assertDontSee(__('Save item'));
    put(tenantUrl('sunrise', "/restaurant/menu/items/{$item->id}"), itemForm($mains->id))->assertForbidden();
    post(tenantUrl('sunrise', '/restaurant/menu/categories'), ['name' => ['en' => 'X'], 'colour' => 'primary'])->assertForbidden();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/restaurant/menu/items'))->assertForbidden();
});

it('does not show another tenant\'s menu', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = booking(fn (): MenuItem => MenuItem::factory()->create(), 'greenvalley');
    staffUser(DefaultRole::FnbManager);
    ['mains' => $mains] = menuSetup();

    get(tenantUrl('sunrise', "/restaurant/menu/items/{$theirs->id}"))->assertNotFound();
    post(tenantUrl('sunrise', '/restaurant/menu/items'), itemForm($mains->id, ['kind' => 'combo', 'variants' => [], 'components' => [['item_id' => $theirs->id]]]))
        ->assertSessionHasErrors('components.0.item_id');
});
