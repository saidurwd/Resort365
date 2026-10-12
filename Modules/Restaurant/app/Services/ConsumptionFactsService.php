<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Restaurant\Contracts\ConsumptionFacts;
use Modules\Restaurant\DTOs\ConsumptionFact;
use Modules\Restaurant\DTOs\WastageFact;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\SaleConsumption;
use Modules\Restaurant\Models\WastageEntry;

class ConsumptionFactsService implements ConsumptionFacts
{
    public function __construct(private readonly ItemCatalog $items) {}

    public function consumption(int $billId): ?ConsumptionFact
    {
        $bill = PosBill::query()->find($billId);
        $rows = SaleConsumption::query()->where('pos_bill_id', $billId)->get();

        if (! $bill instanceof PosBill || $rows->isEmpty()) {
            return null;
        }

        $classes = $this->items->many($rows->pluck('item_id')->all());
        $byClass = [];

        foreach ($rows as $row) {
            $class = $classes[$row->item_id]->accountClass ?? 'operating';
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($row->total_cost)->toScale(2);
        }

        return new ConsumptionFact($bill->id, $bill->bill_no, $bill->property_id, $bill->outlet_id, Outlet::query()->find($bill->outlet_id)->name ?? '', $bill->business_date->toDateString(), $bill->is_complimentary, $byClass);
    }

    public function wastage(int $wastageId): ?WastageFact
    {
        $entry = WastageEntry::query()->with('lines')->find($wastageId);

        if (! $entry instanceof WastageEntry) {
            return null;
        }

        $classes = $this->items->many($entry->lines->pluck('item_id')->all());
        $byClass = [];

        foreach ($entry->lines as $line) {
            $class = $classes[$line->item_id]->accountClass ?? 'operating';
            $byClass[$class] = (string) BigDecimal::of($byClass[$class] ?? '0')->plus($line->total_cost)->toScale(2);
        }

        return new WastageFact($entry->id, $entry->wastage_no, $entry->property_id, $entry->wasted_on->toDateString(), $byClass);
    }

    public function history(): array
    {
        return [
            'bills' => SaleConsumption::query()->distinct()->orderBy('pos_bill_id')->pluck('pos_bill_id')->map(fn ($id): int => (int) $id)->all(),
            'wastage' => WastageEntry::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ];
    }
}
