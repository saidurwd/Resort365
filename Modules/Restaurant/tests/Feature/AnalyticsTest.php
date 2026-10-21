<?php

/*
| F&B analytics (Step 7.2): menu engineering and trends over real settled bills. "Done when": the menu engineering chart classifies
| the dishes correctly.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Services\Reports\FnbTrends;
use Modules\Restaurant\Services\Reports\MenuEngineeringReport;

use function Pest\Laravel\get;

require_once __DIR__.'/../Support/recipe-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

/**
 * Sells at the Main Restaurant: 10 burgers (450, costs 209.64), 8 prawns (600, costs 400), 2 salads (400, costs 40), 1 pasta (250,
 * costs 200) and 3 naan (100, no recipe). 21 portions with a known cost, revenue 10,350 and cost 5,576.40.
 *
 * @return array<string, mixed>
 */
function anSales(): array
{
    $setup = billSetup();
    $store = rcKitchen($setup);
    [$burger] = rcBurger($setup, $store);
    $prawns = rcDish($setup, $store, 'PRAWNS', 'Garlic prawns', '600.00', 'Tiger prawn', '1000', '0.4');
    $salad = rcDish($setup, $store, 'SALAD', 'Garden salad', '400.00', 'Lettuce', '1000', '0.04');
    $pasta = rcDish($setup, $store, 'PASTA', 'Beef pasta', '250.00', 'Pasta', '1000', '0.2');
    cashierOn($setup);

    foreach ([[$burger, 10, 'T1'], [$prawns, 8, 'T2'], [$salad, 2, 'T3'], [$pasta, 1, 'T1']] as [$dish, $quantity, $table]) {
        rcSell($setup, $dish, $quantity, $table);
    }

    rcSell($setup, MenuItem::query()->findOrFail($setup['items']['naan']), 3, 'T2');

    return $setup;
}

function anDate(): string
{
    return booking(fn (): string => PosBill::query()->firstOrFail()->business_date->toDateString());
}

it('classes the dishes by popularity and margin, and lists the dishes without a recipe apart', function (): void {
    $setup = anSales();
    $date = anDate();
    $built = booking(fn (): array => app(MenuEngineeringReport::class)->build(bookingIds()['property'], $date, $date, [$setup['terminal']->outlet_id], null));
    $classes = collect($built['table']['rows'])->mapWithKeys(fn (array $row): array => [$row[0] => $row[8]])->all();

    // 21 portions: the burger (47.6%) and prawns (38.1%) are popular, the salad (9.5%) and pasta (4.8%) are not; the average margin is 227.31,
    // which the burger (240.36) and the salad (360.00) clear, and the prawns (200.00) and the pasta (50.00) do not.
    expect($classes)->toBe(['Beef burger' => 'Star', 'Garlic prawns' => 'Plowhorse', 'Garden salad' => 'Puzzle', 'Beef pasta' => 'Dog'])
        ->and(array_values(array_diff_key($built['table']['rows'][0], [1 => 1])))->toBe(['Beef burger', '10', '450.00', '209.64', '240.36', '47.6%', '4,500.00', 'Star'])
        ->and($built['table']['summary'][4])->toBe(['label' => 'Popular from', 'value' => '17.5%'])->and($built['table']['summary'][5])->toBe(['label' => 'High margin from', 'value' => '227.31'])
        ->and($built['table']['summary'][0])->toBe(['label' => 'Stars', 'value' => '1'])
        ->and($built['unknown'])->toHaveCount(1)->and($built['unknown'][0]['name'])->toBe('Naan')->and($built['unknown'][0]['quantity'])->toBe('3.000');
});

it('draws a scatter chart of popularity against margin with the two cuts', function (): void {
    $setup = anSales();
    $date = anDate();
    $chart = booking(fn (): array => app(MenuEngineeringReport::class)->build(bookingIds()['property'], $date, $date, [$setup['terminal']->outlet_id], null))['chart'];
    $datasets = array_column($chart['data']['datasets'], null, 'label');

    expect($chart['type'])->toBe('scatter')->and($chart['pointLabels'])->toBeTrue()->and($datasets['Star']['data'][0])->toBe(['x' => 47.6, 'y' => 240.36, 'name' => 'Beef burger'])
        ->and($datasets['Dog']['data'][0]['name'])->toBe('Beef pasta')->and($datasets['Popularity cut']['data'][0]['x'])->toBe(17.5)->and($datasets['Margin cut']['data'][0]['y'])->toBe(227.31);
});

it('limits the classes to a menu category', function (): void {
    $setup = anSales();
    $date = anDate();
    $category = booking(function (): int {
        $category = MenuCategory::factory()->create(['name' => ['en' => 'Grill']]);
        MenuItem::query()->where('code', 'PRAWNS')->update(['menu_category_id' => $category->id]);

        return $category->id;
    });
    $built = booking(fn (): array => app(MenuEngineeringReport::class)->build(bookingIds()['property'], $date, $date, [$setup['terminal']->outlet_id], $category));

    expect(array_column($built['table']['rows'], 0))->toBe(['Garlic prawns'])->and($built['table']['rows'][0][8])->toBe('Star')->and($built['unknown'])->toBe([]);
});

it('adds the day\'s sales, covers and bills up for the trends', function (): void {
    $setup = anSales();
    $date = anDate();
    $built = booking(fn (): array => app(FnbTrends::class)->build(bookingIds()['property'], $date, $date, [$setup['terminal']->outlet_id], 'day'));

    // 4,500 + 4,800 + 800 + 250 + 300 over 5 bills, each opened for 2 covers.
    expect($built['table']['totals'])->toBe(['Total', '10,650.00', '10', '5', '1,065.00', '—'])->and($built['table']['rows'])->toHaveCount(1)->and($built['table']['rows'][0][1])->toBe('10,650.00')
        ->and($built['chart']['data']['datasets'][0]['data'])->toBe([10650.0]);
});

it('shows the two reports on screen and as CSV to the people who may see reports', function (): void {
    anSales();
    $date = anDate();
    staffUser(DefaultRole::TenantOwner);

    get(tenantUrl('sunrise', "/restaurant/reports/menu-engineering?from={$date}&to={$date}"))->assertOk()->assertSeeHtml('data-report-tab="menu-engineering"')->assertSeeHtml('data-chart="menu-engineering"')->assertSeeHtml('data-class-advice')
        ->assertSeeHtml('data-unknown')->assertSee('Beef burger')->assertSee('Plowhorse')->assertSee('Naan');
    get(tenantUrl('sunrise', "/restaurant/reports/trends?from={$date}&to={$date}&grain=week"))->assertOk()->assertSeeHtml('data-chart="trends"')->assertSee('10,650.00')->assertSee('Spend per cover');
    $csv = get(tenantUrl('sunrise', "/restaurant/reports/menu-engineering?from={$date}&to={$date}&export=csv"));

    expect($csv->streamedContent())->toContain('Dish,Category,Sold')->toContain('Beef burger')->toContain('Star');
    get(tenantUrl('sunrise', '/restaurant/reports/trends?from=2026-03-31&to=2026-03-01'))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', '/restaurant/reports/trends?grain=fortnight'))->assertSessionHasErrors('grain');
});

it('shows the last weeks when no dates are asked for', function (): void {
    anSales();
    staffUser(DefaultRole::TenantOwner);

    get(tenantUrl('sunrise', '/restaurant/reports/trends'))->assertOk()->assertSeeHtml('data-report-table="trends"');
    get(tenantUrl('sunrise', '/restaurant/reports/menu-engineering'))->assertOk()->assertSeeHtml('data-report-table="menu-engineering"');
});

it('keeps the reports from those who may not see them', function (): void {
    anSales();
    staffUser(DefaultRole::FrontDeskAgent);

    get(tenantUrl('sunrise', '/restaurant/reports/menu-engineering'))->assertForbidden();
    get(tenantUrl('sunrise', '/restaurant/reports/trends'))->assertForbidden();
});
