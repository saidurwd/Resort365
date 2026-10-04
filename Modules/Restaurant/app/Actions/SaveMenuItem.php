<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\ComboComponent;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuItemVariant;

/**
 * Creates or changes a menu item with its variants, modifier groups and (for a combo) components, in
 * one transaction (ARCHITECTURE §5.10.2). Variants are matched by name, so a renamed list keeps the
 * outlet prices of the variants still there; a removed variant loses its prices. A combo needs at
 * least one component and cannot contain itself or another combo.
 */
class SaveMenuItem extends Action
{
    /**
     * @param  array{menu_category_id: int, code: string, name: array<string, string|null>, description?: array<string, string|null>, course: string,
     *     tax_category_id?: int|null, kind: string, dietary_tags?: list<string>, allergens?: list<string>, sort_order?: int, is_active?: bool,
     *     variants?: list<string>, modifier_group_ids?: list<int>, components?: list<array{item_id: int, variant_id?: int|null, quantity?: int}>}  $data
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(int $propertyId, ?MenuItem $item, array $data): MenuItem
    {
        $kind = MenuItemKind::from($data['kind']);
        $components = $kind === MenuItemKind::Combo ? ($data['components'] ?? []) : [];

        if ($kind === MenuItemKind::Combo && $components === []) {
            throw new RestaurantSetupInvalid(__('A combo needs the items it is made of.'));
        }

        if ($components !== [] && MenuItem::query()->whereIn('id', array_column($components, 'item_id'))
            ->where(fn ($query) => $query->where('kind', MenuItemKind::Combo->value)->when($item instanceof MenuItem, fn ($inner) => $inner->orWhere('id', $item?->id)))->exists()) {
            throw new RestaurantSetupInvalid(__('A combo cannot contain another combo or itself.'));
        }

        return $this->transaction(function () use ($propertyId, $item, $data, $kind, $components): MenuItem {
            $clean = fn (array $values): array => array_filter(array_map(fn (?string $value): string => trim((string) $value), $values), fn (string $value): bool => $value !== '');
            $item ??= new MenuItem(['property_id' => $propertyId]);
            $item->fill([
                'menu_category_id' => $data['menu_category_id'], 'code' => strtoupper($data['code']), 'name' => $clean($data['name']),
                'description' => $clean($data['description'] ?? []) ?: null, 'course' => $data['course'], 'tax_category_id' => $data['tax_category_id'] ?? null,
                'kind' => $kind, 'dietary_tags' => array_values(array_unique($data['dietary_tags'] ?? [])), 'allergens' => array_values(array_unique($data['allergens'] ?? [])),
                'sort_order' => $data['sort_order'] ?? $item->sort_order ?? 0, 'is_active' => $data['is_active'] ?? true,
            ])->save();

            $this->variants($item, array_values(array_unique(array_filter(array_map(trim(...), $data['variants'] ?? [])))));
            $this->modifierGroups($item, array_values(array_unique(array_map(intval(...), $data['modifier_group_ids'] ?? []))));

            ComboComponent::query()->where('menu_item_id', $item->id)->delete();

            foreach ($components as $order => $component) {
                ComboComponent::query()->create([
                    'property_id' => $item->property_id, 'menu_item_id' => $item->id, 'component_item_id' => $component['item_id'],
                    'component_variant_id' => $component['variant_id'] ?? null, 'quantity' => max(1, (int) ($component['quantity'] ?? 1)), 'sort_order' => $order,
                ]);
            }

            return $item;
        });
    }

    /**
     * @param  list<string>  $names
     */
    private function variants(MenuItem $item, array $names): void
    {
        $existing = $item->variants()->get()->keyBy(fn (MenuItemVariant $variant): string => mb_strtolower($variant->name));

        foreach ($existing as $key => $variant) {
            if (! in_array($key, array_map(mb_strtolower(...), $names), true)) {
                $variant->delete();
            }
        }

        foreach ($names as $order => $name) {
            $variant = $existing->get(mb_strtolower($name)) ?? new MenuItemVariant(['property_id' => $item->property_id, 'menu_item_id' => $item->id]);
            $variant->fill(['name' => $name, 'sort_order' => $order])->save();
        }

        // Items without variants are priced on variant 0; with variants, only per variant.
        DB::table('outlet_menu_items')->where('menu_item_id', $item->id)->where('variant_key', $names === [] ? '!=' : '=', 0)->delete();
    }

    /**
     * @param  list<int>  $groupIds
     */
    private function modifierGroups(MenuItem $item, array $groupIds): void
    {
        $item->modifierGroups()->sync(collect($groupIds)->mapWithKeys(fn (int $id, int $order): array => [$id => ['tenant_id' => $item->tenant_id, 'sort_order' => $order]])->all());
    }
}
