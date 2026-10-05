<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\MenuCatalog;
use Modules\Restaurant\Services\ModifierRules;
use Modules\Restaurant\Services\OrderLinePricing;
use Modules\Restaurant\Services\OrderTotals;

/**
 * Adds an item to an open order (ARCHITECTURE §5.10.2, §5.10.5). The server decides the price: the
 * outlet's price of the item (variant) adjusted by the schedule running now; refused when the outlet
 * does not sell it, it is sold out (86) or out of schedule. The modifiers chosen must fit the item's
 * groups (ModifierRules) and add their prices. An open item is priced by the staff member (who needs
 * restaurant.order.open-item). The line is made at the outlet's station on the price list (else the
 * outlet's first station) and stays pending until sent.
 */
class AddOrderLine extends Action
{
    public function __construct(
        private readonly MenuCatalog $catalog,
        private readonly ModifierRules $rules,
        private readonly OrderLinePricing $pricing,
        private readonly OrderTotals $totals,
    ) {}

    /**
     * @param  array{item_id: int, variant_id?: int|null, quantity?: int, modifier_ids?: list<int>, course?: string|null, seat?: int|null, notes?: string|null, held?: bool, open_price?: string|null}  $data
     *
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, array $data, int $userId, bool $mayPriceOpenItems): PosOrderLine
    {
        if (! $order->isOpen()) {
            throw new PosNotAllowed(__('This order is closed.'));
        }

        $outlet = $order->outlet;
        $item = MenuItem::query()->where('is_active', true)->with('variants')->find($data['item_id']);
        $variantId = $data['variant_id'] ?? null;
        $row = $item instanceof MenuItem ? OutletMenuItem::query()->where('outlet_id', $outlet->id)->where('menu_item_id', $item->id)->where('variant_key', $variantId ?? 0)->first() : null;

        if (! $item instanceof MenuItem || ! $row instanceof OutletMenuItem) {
            throw new PosNotAllowed(__('This outlet does not sell that item.'));
        }

        if (! $row->is_available) {
            throw new PosNotAllowed(__(':item is sold out.', ['item' => $item->translated('name')]));
        }

        $price = $this->catalog->priceNow($row, $this->catalog->schedules($outlet), $this->catalog->now($outlet));

        if ($price === null) {
            throw new PosNotAllowed(__(':item is not served at this time.', ['item' => $item->translated('name')]));
        }

        if ($item->kind === MenuItemKind::Open) {
            if (! $mayPriceOpenItems) {
                throw new PosNotAllowed(__('Only a manager or cashier can price an open item.'));
            }

            if (! is_numeric($data['open_price'] ?? null) || BigDecimal::of((string) $data['open_price'])->isNegativeOrZero()) {
                throw new PosNotAllowed(__('Enter the price of the open item.'));
            }

            $price = (string) BigDecimal::of((string) $data['open_price'])->toScale(2);
        }

        $check = $this->rules->check($this->catalog->groups([$item->id])[$item->id] ?? [], array_map(intval(...), $data['modifier_ids'] ?? []));

        if ($check['errors'] !== []) {
            throw new PosNotAllowed(implode(' ', $check['errors']));
        }

        $quantity = max(1, min(99, (int) ($data['quantity'] ?? 1)));
        $money = $this->pricing->price($price, $quantity, array_column($check['selected'], 'price'));
        $station = $row->kitchen_station_id ?? KitchenStation::query()->where('outlet_id', $outlet->id)->orderBy('sort_order')->orderBy('id')->value('id');
        $variant = $variantId !== null ? $item->variants->firstWhere('id', $variantId) : null;

        return $this->transaction(function () use ($order, $item, $variant, $quantity, $price, $money, $check, $station, $data, $userId): PosOrderLine {
            $line = PosOrderLine::query()->create([
                'property_id' => $order->property_id, 'pos_order_id' => $order->id, 'menu_item_id' => $item->id, 'menu_item_variant_id' => $variant?->id,
                'name_snapshot' => $item->translated('name'), 'variant_snapshot' => $variant?->name, 'quantity' => $quantity, 'unit_price' => $price,
                'modifiers' => array_map(fn (array $modifier): array => ['group' => $modifier['group'], 'name' => $modifier['name'], 'price' => (string) $modifier['price']], $check['selected']) ?: null,
                'modifier_total' => $money['modifier_total'], 'line_total' => $money['line_total'], 'course' => $data['course'] ?? $item->course->value,
                'seat_no' => $data['seat'] ?? null, 'kitchen_station_id' => $station, 'status' => OrderLineStatus::Pending, 'is_held' => (bool) ($data['held'] ?? false),
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? mb_substr(trim((string) $data['notes']), 0, 300) : null, 'added_by' => $userId,
            ]);
            $this->totals->refresh($order);

            return $line;
        });
    }
}
