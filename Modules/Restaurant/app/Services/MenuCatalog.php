<?php

namespace Modules\Restaurant\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * What an outlet sells right now and at what price (the POS menu): items of its price list with the
 * price of the schedule running now (MenuAvailability), whether they are sold out (86) or out of
 * schedule, their variants and modifier groups; and the categories that have them. Times are the
 * property's local time.
 */
class MenuCatalog
{
    public function __construct(
        private readonly MenuAvailability $availability,
        private readonly PropertyDirectory $properties,
    ) {}

    public function now(Outlet $outlet): CarbonImmutable
    {
        return CarbonImmutable::now($this->properties->find($outlet->property_id)->timezone ?? 'UTC');
    }

    /**
     * @return array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>
     */
    public function schedules(Outlet $outlet): array
    {
        return MenuSchedule::query()->where('outlet_id', $outlet->id)->get()->mapWithKeys(fn (MenuSchedule $schedule): array => [$schedule->id => [
            'id' => $schedule->id, 'days' => $schedule->days_of_week, 'start' => $schedule->start_time, 'end' => $schedule->end_time,
            'adjustment' => (string) $schedule->price_adjustment_percent, 'active' => $schedule->is_active,
        ]])->all();
    }

    /**
     * The price of a price-list row now, or null when it is out of schedule.
     *
     * @param  array<int, array{id: int, days: list<string>, start: string, end: string, adjustment: string, active: bool}>  $schedules
     */
    public function priceNow(OutletMenuItem $row, array $schedules, CarbonImmutable $at): ?string
    {
        return $this->availability->price($row->price, array_map(intval(...), $row->schedule_ids ?? []), $schedules, $at);
    }

    /**
     * The modifier groups of items, as ModifierRules wants them.
     *
     * @param  list<int>  $itemIds
     * @return array<int, list<array{id: int, name: string, min: int, max: int, options: array<int, array{name: string, price: string, active: bool}>}>> item id => groups
     */
    public function groups(array $itemIds): array
    {
        $items = MenuItem::query()->whereIn('id', $itemIds)->with('modifierGroups.modifiers')->get();

        return $items->mapWithKeys(fn (MenuItem $item): array => [$item->id => $item->modifierGroups->map(fn (ModifierGroup $group): array => [
            'id' => $group->id, 'name' => $group->name, 'min' => $group->min_select, 'max' => $group->max_select,
            'options' => $group->modifiers->mapWithKeys(fn (Modifier $modifier): array => [$modifier->id => ['name' => $modifier->name, 'price' => $modifier->price_delta, 'active' => $modifier->is_active]])->all(),
        ])->values()->all()])->all();
    }

    /**
     * The POS menu of an outlet now.
     *
     * @return array{categories: list<array{id: int, name: string, colour: string}>, items: list<array<string, mixed>>}
     */
    public function forOutlet(Outlet $outlet): array
    {
        $at = $this->now($outlet);
        $schedules = $this->schedules($outlet);
        $rows = OutletMenuItem::query()->where('outlet_id', $outlet->id)->get()->groupBy('menu_item_id');
        $items = MenuItem::query()->whereIn('id', $rows->keys())->where('is_active', true)->with(['variants', 'category'])->orderBy('sort_order')->orderBy('code')->get();
        $groups = $this->groups($items->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $menu = $items->map(function (MenuItem $item) use ($rows, $at, $schedules, $groups): array {
            $prices = $rows->get($item->id)->keyBy('variant_key');
            $variants = $item->variants->isEmpty() ? [null] : $item->variants->all();
            $options = [];

            foreach ($variants as $variant) {
                $row = $prices->get($variant->id ?? 0);

                if ($row instanceof OutletMenuItem) {
                    $price = $this->priceNow($row, $schedules, $at);
                    $options[] = ['id' => $variant?->id, 'name' => $variant?->name, 'price' => $price ?? $row->price, 'sold_out' => ! $row->is_available, 'out_of_schedule' => $price === null];
                }
            }

            return [
                'id' => $item->id, 'code' => $item->code, 'name' => $item->translated('name'), 'category_id' => $item->menu_category_id, 'kind' => $item->kind->value,
                'course' => $item->course->value, 'open' => $item->kind === MenuItemKind::Open, 'dietary' => $item->dietary_tags ?? [], 'allergens' => $item->allergens ?? [],
                'variants' => $options, 'groups' => array_map(fn (array $group): array => ['id' => $group['id'], 'name' => $group['name'], 'min' => $group['min'], 'max' => $group['max'],
                    'options' => collect($group['options'])->filter(fn (array $option): bool => $option['active'])->map(fn (array $option, int $id): array => ['id' => $id, 'name' => $option['name'], 'price' => $option['price']])->values()->all()],
                    $groups[$item->id] ?? []),
            ];
        })->filter(fn (array $item): bool => $item['variants'] !== [])->values();

        $categoryIds = $menu->pluck('category_id')->unique();

        return [
            'categories' => MenuCategory::query()->whereIn('id', $categoryIds)->orderBy('sort_order')->get()
                ->map(fn (MenuCategory $category): array => ['id' => $category->id, 'name' => $category->translated('name'), 'colour' => $category->colour])->values()->all(),
            'items' => $menu->all(),
        ];
    }
}
