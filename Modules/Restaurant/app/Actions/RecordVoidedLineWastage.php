<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\DTOs\NewWastage;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Services\RecipeBook;

/**
 * A line voided after it was already made is food thrown away (Step 5.7): its recipe times its quantity is written off
 * from the outlet's store. Nothing to write off when the item has no recipe or the outlet has no store. Once per line.
 */
class RecordVoidedLineWastage extends Action
{
    public function __construct(
        private readonly RecordWastage $wastage,
        private readonly RecipeBook $book,
    ) {}

    public function handle(PosOrderLine $line, ?int $userId = null): ?WastageEntry
    {
        $outlet = Outlet::query()->find($line->order()->value('outlet_id'));
        $item = MenuItem::query()->find($line->menu_item_id);

        if (! $outlet instanceof Outlet || $outlet->store_id === null || ! $item instanceof MenuItem || WastageEntry::query()->where('source_type', 'pos_order_line')->where('source_id', $line->id)->exists()) {
            return null;
        }

        if ($this->book->portion($item, $line->menu_item_variant_id, $this->book->modifierIds($line)) === []) {
            return null;
        }

        try {
            return $this->wastage->handle(new NewWastage(
                $outlet->store_id, WastageReason::Other, $userId ?? (int) $line->voided_by, dishes: [['menu_item_id' => $item->id, 'variant_id' => $line->menu_item_variant_id, 'portions' => (string) $line->quantity]],
                wastedOn: $line->voided_at?->toDateString(), notes: __('Voided after it was made: :name', ['name' => $line->name_snapshot]), sourceType: 'pos_order_line', sourceId: $line->id, allowNegative: true,
            ));
        } catch (RestaurantSetupInvalid) {
            return null;
        }
    }
}
