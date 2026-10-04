<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Restaurant\Actions\MarkSoldOut;
use Modules\Restaurant\Actions\SaveMenuCategory;
use Modules\Restaurant\Actions\SaveMenuItem;
use Modules\Restaurant\Actions\SaveMenuSchedule;
use Modules\Restaurant\Actions\SaveModifierGroup;
use Modules\Restaurant\Actions\SavePriceList;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Rodela's demo menu (Step 3.2): 63 items in 11 categories (Food and Drinks), with variants (Half /
 * Full, Glass / Jug, Regular / Large), modifier groups (cooking level, spice level, add-ons, milk),
 * two combos, an open item and direct-stock soft drinks, some names in Bangla too. Priced at three
 * outlets: the Main Restaurant sells everything (breakfast 07:00–10:30, covered by meal plans; the
 * lobster and lava cake are sold out), the Pool Bar drinks and snacks for 10% more (happy hour
 * 17:00–19:00 at −20% on juices and mocktails), Room Service the food and drinks for 15% more.
 */
final class DemoMenu
{
    /**
     * category => [code, name, bangla name, course, kind, tags, allergens, variants, Main Restaurant price(s), modifier groups]
     *
     * @var array<string, list<array{string, string, string|null, string, string, list<string>, list<string>, list<string>, list<int>, list<string>}>>
     */
    private const array MENU = [
        'Breakfast' => [
            ['BF01', 'Paratha with egg', 'পরোটা ও ডিম', 'main', 'dish', ['halal'], ['gluten', 'eggs'], [], [250], []],
            ['BF02', 'Khichuri with beef bhuna', 'খিচুড়ি ও গরুর ভুনা', 'main', 'dish', ['halal', 'spicy'], [], [], [450], ['Spice level']],
            ['BF03', 'Continental breakfast', null, 'main', 'dish', [], ['gluten', 'eggs', 'milk'], [], [650], []],
            ['BF04', 'Pancakes with honey', null, 'main', 'dish', ['vegetarian'], ['gluten', 'eggs', 'milk'], [], [350], []],
            ['BF05', 'Omelette', 'অমলেট', 'main', 'dish', ['vegetarian', 'gluten_free'], ['eggs'], [], [200], ['Add-ons']],
            ['BF06', 'Fruit platter', 'ফলের প্লেট', 'main', 'dish', ['vegan', 'gluten_free'], [], [], [300], []],
        ],
        'Starters & Soups' => [
            ['ST01', 'Thai soup', 'থাই স্যুপ', 'starter', 'dish', ['spicy'], ['crustaceans', 'fish'], ['Half', 'Full'], [280, 480], []],
            ['ST02', 'Corn soup', 'কর্ন স্যুপ', 'starter', 'dish', ['vegetarian'], ['eggs'], ['Half', 'Full'], [250, 420], []],
            ['ST03', 'Vegetable pakora', 'সবজি পাকোড়া', 'starter', 'dish', ['vegan'], ['gluten'], [], [250], []],
            ['ST04', 'Chicken wings', null, 'starter', 'dish', ['halal', 'spicy'], [], [], [450], ['Spice level']],
            ['ST05', 'Prawn tempura', null, 'starter', 'dish', [], ['crustaceans', 'gluten', 'eggs'], [], [550], []],
            ['ST06', 'Caesar salad', null, 'starter', 'dish', [], ['eggs', 'fish', 'milk', 'gluten'], [], [450], []],
            ['ST07', 'Beef shami kabab', 'শামি কাবাব', 'starter', 'dish', ['halal'], ['eggs'], [], [380], []],
            ['ST08', 'Spring roll', null, 'starter', 'dish', ['vegetarian'], ['gluten'], [], [280], []],
        ],
        'Bangladeshi' => [
            ['BD01', 'Chicken curry', 'মুরগির তরকারি', 'main', 'dish', ['halal', 'spicy'], [], ['Half', 'Full'], [380, 650], ['Spice level']],
            ['BD02', 'Beef rezala', 'গরুর রেজালা', 'main', 'dish', ['halal'], ['milk', 'nuts'], ['Half', 'Full'], [450, 800], []],
            ['BD03', 'Mutton kacchi biryani', 'খাসির কাচ্চি বিরিয়ানি', 'main', 'dish', ['halal'], ['milk'], ['Half', 'Full'], [520, 950], []],
            ['BD04', 'Shorshe ilish', 'সর্ষে ইলিশ', 'main', 'dish', ['gluten_free'], ['fish', 'mustard'], [], [850], []],
            ['BD05', 'Dal makhani', 'ডাল মাখানি', 'main', 'dish', ['vegetarian', 'gluten_free'], ['milk'], [], [280], []],
            ['BD06', 'Mixed vegetable bhaji', 'মিক্সড সবজি ভাজি', 'main', 'dish', ['vegan', 'gluten_free'], [], [], [250], []],
            ['BD07', 'Chicken roast', 'মুরগির রোস্ট', 'main', 'dish', ['halal'], ['milk', 'nuts'], [], [480], []],
            ['BD08', 'Morog polao', 'মোরগ পোলাও', 'main', 'dish', ['halal'], ['milk'], [], [550], []],
        ],
        'Seafood' => [
            ['SF01', 'Grilled lobster', 'গ্রিলড লবস্টার', 'main', 'dish', ['gluten_free'], ['crustaceans', 'milk'], [], [3500], []],
            ['SF02', 'Garlic prawns', null, 'main', 'dish', ['gluten_free'], ['crustaceans', 'milk'], [], [1200], ['Spice level']],
            ['SF03', 'Fish & chips', null, 'main', 'dish', [], ['fish', 'gluten', 'eggs'], [], [750], []],
            ['SF04', 'Rupchanda fry', 'রূপচাঁদা ভাজা', 'main', 'dish', ['gluten_free'], ['fish'], [], [900], []],
            ['SF05', 'Crab masala', 'কাঁকড়া মসলা', 'main', 'dish', ['spicy'], ['crustaceans'], [], [1400], []],
            ['SF06', 'Fried calamari', null, 'main', 'dish', [], ['molluscs', 'gluten'], [], [850], []],
        ],
        'Grill' => [
            ['GR01', 'Beef tenderloin steak', null, 'main', 'dish', ['halal', 'gluten_free'], ['milk'], [], [1800], ['Cooking level']],
            ['GR02', 'BBQ chicken', 'বারবিকিউ চিকেন', 'main', 'dish', ['halal', 'spicy'], [], ['Half', 'Full'], [550, 950], ['Spice level']],
            ['GR03', 'Lamb chops', null, 'main', 'dish', ['halal', 'gluten_free'], [], [], [1600], ['Cooking level']],
            ['GR04', 'Beef burger', null, 'main', 'dish', ['halal'], ['gluten', 'milk', 'sesame'], [], [650], ['Cooking level', 'Add-ons']],
            ['GR05', 'Grilled vegetable platter', null, 'main', 'dish', ['vegan', 'gluten_free'], [], [], [450], []],
            ['GR06', 'Mixed grill platter', null, 'main', 'dish', ['halal'], ['milk'], [], [2200], ['Spice level']],
        ],
        'Pasta & Rice' => [
            ['PR01', 'Spaghetti bolognese', null, 'main', 'dish', ['halal'], ['gluten', 'milk'], [], [650], []],
            ['PR02', 'Fettuccine alfredo', null, 'main', 'dish', ['vegetarian'], ['gluten', 'milk'], [], [600], []],
            ['PR03', 'Chicken fried rice', 'চিকেন ফ্রাইড রাইস', 'main', 'dish', ['halal'], ['eggs', 'soybeans'], [], [450], []],
            ['PR04', 'Vegetable fried rice', 'সবজি ফ্রাইড রাইস', 'main', 'dish', ['vegetarian'], ['eggs', 'soybeans'], [], [350], []],
            ['PR05', 'Plain rice', 'সাদা ভাত', 'side', 'dish', ['vegan', 'gluten_free'], [], [], [120], []],
            ['PR06', 'Naan', 'নান', 'side', 'dish', ['vegetarian'], ['gluten', 'milk'], ['Plain', 'Butter', 'Garlic'], [80, 100, 120], []],
        ],
        'Desserts' => [
            ['DS01', 'Chocolate lava cake', null, 'dessert', 'dish', ['vegetarian'], ['gluten', 'eggs', 'milk'], [], [450], []],
            ['DS02', 'Rasmalai', 'রসমালাই', 'dessert', 'dish', ['vegetarian', 'gluten_free'], ['milk', 'nuts'], [], [250], []],
            ['DS03', 'Firni', 'ফিরনি', 'dessert', 'dish', ['vegetarian', 'gluten_free'], ['milk', 'nuts'], [], [200], []],
            ['DS04', 'Ice cream', 'আইসক্রিম', 'dessert', 'dish', ['vegetarian'], ['milk'], ['Single', 'Double'], [180, 300], []],
            ['DS05', 'Mishti doi', 'মিষ্টি দই', 'dessert', 'dish', ['vegetarian', 'gluten_free'], ['milk'], [], [150], []],
        ],
        'Hot drinks' => [
            ['HD01', 'Coffee', 'কফি', 'drink', 'dish', ['vegetarian'], ['milk'], ['Regular', 'Large'], [220, 300], ['Milk choice']],
            ['HD02', 'Cappuccino', null, 'drink', 'dish', ['vegetarian'], ['milk'], ['Regular', 'Large'], [280, 360], ['Milk choice']],
            ['HD03', 'Masala tea', 'মসলা চা', 'drink', 'dish', ['vegetarian'], ['milk'], [], [120], []],
            ['HD04', 'Green tea', 'গ্রিন টি', 'drink', 'dish', ['vegan'], [], [], [150], []],
        ],
        'Fresh juices' => [
            ['FJ01', 'Orange juice', 'কমলার রস', 'drink', 'dish', ['vegan'], [], ['Glass', 'Jug'], [250, 800], []],
            ['FJ02', 'Watermelon juice', 'তরমুজের রস', 'drink', 'dish', ['vegan'], [], ['Glass', 'Jug'], [220, 700], []],
            ['FJ03', 'Mango lassi', 'আমের লাচ্ছি', 'drink', 'dish', ['vegetarian'], ['milk'], [], [280], []],
            ['FJ04', 'Lemon mint', 'লেবু পুদিনা', 'drink', 'dish', ['vegan'], [], [], [180], []],
        ],
        'Mocktails' => [
            ['MK01', 'Virgin mojito', null, 'drink', 'dish', ['vegan'], [], [], [350], []],
            ['MK02', 'Blue lagoon', null, 'drink', 'dish', ['vegan'], [], [], [380], []],
            ['MK03', 'Virgin piña colada', null, 'drink', 'dish', ['vegetarian'], ['milk'], [], [420], []],
        ],
        'Soft drinks' => [
            ['SD01', 'Mineral water', 'মিনারেল ওয়াটার', 'drink', 'direct_stock', ['vegan'], [], ['500 ml', '1.5 l'], [40, 80], []],
            ['SD02', 'Coca-Cola (can)', null, 'drink', 'direct_stock', ['vegan'], [], [], [80], []],
            ['SD03', 'Sprite (can)', null, 'drink', 'direct_stock', ['vegan'], [], [], [80], []],
            ['SD04', 'Soda water', null, 'drink', 'direct_stock', ['vegan'], [], [], [60], []],
        ],
    ];

    /**
     * @var array<string, array{int, int, list<array{string, int}>}> name => [min, max, options (name, price)]
     */
    private const array MODIFIERS = [
        'Cooking level' => [1, 1, [['Rare', 0], ['Medium rare', 0], ['Medium', 0], ['Well done', 0]]],
        'Spice level' => [1, 1, [['Mild', 0], ['Medium', 0], ['Hot', 0], ['Extra hot', 0]]],
        'Add-ons' => [0, 3, [['Extra cheese', 80], ['Fried egg', 40], ['Beef bacon', 120], ['Extra sauce', 30], ['Jalapeños', 40]]],
        'Milk choice' => [0, 1, [['Full cream', 0], ['Low fat', 0], ['Oat milk', 60], ['Almond milk', 60]]],
    ];

    private const array POOL_BAR_SNACKS = ['ST03', 'ST04', 'ST06', 'ST08', 'SF03', 'GR04', 'PR03', 'DS04'];

    private const array DRINK_CATEGORIES = ['Hot drinks', 'Fresh juices', 'Mocktails', 'Soft drinks'];

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (MenuItem::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $food = SaveMenuCategory::make()->handle($propertyId, null, ['name' => ['en' => 'Food', 'bn' => 'খাবার'], 'colour' => 'primary', 'sort_order' => 0]);
            $drinks = SaveMenuCategory::make()->handle($propertyId, null, ['name' => ['en' => 'Drinks', 'bn' => 'পানীয়'], 'colour' => 'info', 'sort_order' => 1]);
            $groups = [];

            foreach (self::MODIFIERS as $name => [$min, $max, $options]) {
                $groups[$name] = SaveModifierGroup::make()->handle($propertyId, null, $name, $min, $max,
                    array_map(fn (array $option): array => ['name' => $option[0], 'price_delta' => (string) $option[1]], $options))->id;
            }

            $items = [];
            $order = 0;

            foreach (self::MENU as $categoryName => $rows) {
                $isDrink = in_array($categoryName, self::DRINK_CATEGORIES, true);
                $category = SaveMenuCategory::make()->handle($propertyId, null, ['name' => ['en' => $categoryName], 'parent_id' => ($isDrink ? $drinks : $food)->id,
                    'colour' => $isDrink ? 'info' : ($categoryName === 'Desserts' ? 'warning' : 'primary'), 'sort_order' => $order++]);

                foreach ($rows as $sort => [$code, $name, $bangla, $course, $kind, $tags, $allergens, $variants, $prices, $modifiers]) {
                    $item = SaveMenuItem::make()->handle($propertyId, null, [
                        'menu_category_id' => $category->id, 'code' => $code, 'name' => array_filter(['en' => $name, 'bn' => $bangla]), 'course' => $course,
                        'kind' => $kind, 'dietary_tags' => $tags, 'allergens' => $allergens, 'variants' => $variants, 'sort_order' => $sort,
                        'modifier_group_ids' => array_map(fn (string $group): int => $groups[$group], $modifiers),
                    ]);
                    $items[$code] = ['item' => $item, 'category' => $categoryName, 'prices' => $prices];
                }
            }

            $chefsSpecial = SaveMenuItem::make()->handle($propertyId, null, ['menu_category_id' => $food->id, 'code' => 'OP01', 'name' => ['en' => 'Chef\'s special'],
                'course' => 'main', 'kind' => 'open', 'sort_order' => 99]);
            $variant = fn (string $code, string $name): ?int => $items[$code]['item']->variants()->where('name', $name)->value('id');
            $setLunch = SaveMenuItem::make()->handle($propertyId, null, ['menu_category_id' => $food->id, 'code' => 'CB01', 'name' => ['en' => 'Set lunch', 'bn' => 'সেট লাঞ্চ'],
                'description' => ['en' => 'Corn soup, chicken curry with rice and a lemon mint.'], 'course' => 'main', 'kind' => 'combo', 'sort_order' => 100,
                'components' => [['item_id' => $items['ST02']['item']->id, 'variant_id' => $variant('ST02', 'Half')], ['item_id' => $items['BD01']['item']->id, 'variant_id' => $variant('BD01', 'Full')],
                    ['item_id' => $items['PR05']['item']->id], ['item_id' => $items['FJ04']['item']->id]]]);
            $kidsMeal = SaveMenuItem::make()->handle($propertyId, null, ['menu_category_id' => $food->id, 'code' => 'CB02', 'name' => ['en' => 'Kids meal'],
                'description' => ['en' => 'Chicken fried rice, a scoop of ice cream and an orange juice.'], 'course' => 'main', 'kind' => 'combo', 'sort_order' => 101,
                'components' => [['item_id' => $items['PR03']['item']->id], ['item_id' => $items['DS04']['item']->id, 'variant_id' => $variant('DS04', 'Single')],
                    ['item_id' => $items['FJ01']['item']->id, 'variant_id' => $variant('FJ01', 'Glass')]]]);
            $items['OP01'] = ['item' => $chefsSpecial, 'category' => 'Specials', 'prices' => [0]];
            $items['CB01'] = ['item' => $setLunch, 'category' => 'Combos', 'prices' => [1100]];
            $items['CB02'] = ['item' => $kidsMeal, 'category' => 'Combos', 'prices' => [650]];

            self::price($propertyId, 'MR', $items, fn (string $code, array $row): bool => true, '0');
            self::price($propertyId, 'PB', $items, fn (string $code, array $row): bool => in_array($row['category'], self::DRINK_CATEGORIES, true) || in_array($code, self::POOL_BAR_SNACKS, true), '10');
            self::price($propertyId, 'RS', $items, fn (string $code, array $row): bool => $row['category'] !== 'Mocktails' && $code !== 'OP01', '15');
        });
    }

    /**
     * Prices one outlet: the chosen items at the Main Restaurant price changed by $percent (whole taka),
     * each at the outlet's station for its category, with breakfast and happy-hour schedules.
     *
     * @param  array<string, array{item: MenuItem, category: string, prices: list<int>}>  $items
     * @param  callable(string, array{item: MenuItem, category: string, prices: list<int>}): bool  $sells
     */
    private static function price(int $propertyId, string $outletCode, array $items, callable $sells, string $percent): void
    {
        $outlet = Outlet::query()->where('property_id', $propertyId)->where('code', $outletCode)->first();

        if (! $outlet instanceof Outlet) {
            return;
        }

        $stations = KitchenStation::query()->where('outlet_id', $outlet->id)->pluck('id', 'name');
        $station = fn (string $category): ?int => match ($outletCode) {
            'MR' => $stations[in_array($category, self::DRINK_CATEGORIES, true) ? 'Bar' : ($category === 'Grill' ? 'Grill' : ($category === 'Desserts' ? 'Pastry' : 'Hot kitchen'))] ?? null,
            default => $stations->first(),
        };
        $breakfast = $outletCode === 'MR' ? SaveMenuSchedule::make()->handle($outlet, null, ['name' => 'Breakfast', 'days_of_week' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_time' => '07:00', 'end_time' => '10:30']) : null;
        $happyHour = $outletCode === 'PB' ? SaveMenuSchedule::make()->handle($outlet, null, ['name' => 'Happy hour', 'days_of_week' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_time' => '17:00', 'end_time' => '19:00', 'price_adjustment_percent' => '-20']) : null;
        $allDay = $outletCode === 'PB' ? SaveMenuSchedule::make()->handle($outlet, null, ['name' => 'All day', 'days_of_week' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'start_time' => '11:00', 'end_time' => '01:00']) : null;
        $rows = [];

        foreach ($items as $code => $row) {
            if (! $sells($code, $row)) {
                continue;
            }

            $variants = $row['item']->variants()->get()->values();
            $schedules = match (true) {
                $breakfast instanceof MenuSchedule && $row['category'] === 'Breakfast' => [$breakfast->id],
                $happyHour instanceof MenuSchedule && $allDay instanceof MenuSchedule && in_array($row['category'], ['Fresh juices', 'Mocktails'], true) => [$allDay->id, $happyHour->id],
                default => [],
            };

            foreach ($variants->isEmpty() ? [null] : $variants->all() as $index => $variant) {
                $price = BigDecimal::of($row['prices'][$index] ?? 0)->multipliedBy(BigDecimal::of(100)->plus($percent))->dividedBy(100, 0, RoundingMode::HalfUp);
                $rows[] = [
                    'item_id' => $row['item']->id, 'variant_id' => $variant?->id, 'on_sale' => true, 'price' => (string) $price, 'station_id' => $station($row['category']),
                    'is_available' => true, 'is_package_eligible' => $outletCode === 'MR' && $row['category'] === 'Breakfast', 'schedule_ids' => $schedules,
                ];
            }
        }

        SavePriceList::make()->handle($outlet, $rows);

        if ($outletCode === 'MR') {
            foreach (['SF01', 'DS01'] as $soldOut) {
                $row = OutletMenuItem::query()->where('outlet_id', $outlet->id)->where('menu_item_id', $items[$soldOut]['item']->id)->first();

                if ($row instanceof OutletMenuItem) {
                    MarkSoldOut::make()->handle($row, true);
                }
            }
        }
    }
}
