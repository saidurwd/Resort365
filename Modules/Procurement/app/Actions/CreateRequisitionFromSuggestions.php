<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\ReorderSuggestions;
use Modules\Procurement\Enums\RequisitionSource;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseRequisition;

/**
 * Turns the chosen reorder suggestions of a property into a purchase requisition (Step 5.5). The same item suggested
 * for several stores becomes one line with the quantities added up; $quantities overrides a suggested quantity.
 */
class CreateRequisitionFromSuggestions extends Action
{
    public function __construct(
        private readonly ReorderSuggestions $suggestions,
        private readonly SavePurchaseRequisition $save,
    ) {}

    /**
     * @param  list<int>  $itemIds  the suggested items to buy
     * @param  array<int, string>  $quantities  item id => quantity to buy instead of the suggestion
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(int $propertyId, array $itemIds, int $userId, array $quantities = [], ?int $departmentId = null): PurchaseRequisition
    {
        $lines = [];

        foreach ($this->suggestions->forProperty($propertyId) as $suggestion) {
            if (! in_array($suggestion->itemId, $itemIds, true)) {
                continue;
            }

            $lines[$suggestion->itemId] ??= ['item_id' => $suggestion->itemId, 'quantity' => '0', 'estimated_unit_cost' => $suggestion->unitCost, 'suggested' => '0'];
            $lines[$suggestion->itemId]['suggested'] = (string) BigDecimal::of($lines[$suggestion->itemId]['suggested'])->plus($suggestion->suggestedQuantity);
        }

        if ($lines === []) {
            throw new ProcurementRuleViolated(__('None of the chosen items is below its reorder level.'));
        }

        $rows = [];

        foreach ($lines as $itemId => $line) {
            $rows[] = ['item_id' => $itemId, 'quantity' => ($quantities[$itemId] ?? '') !== '' ? $quantities[$itemId] : $line['suggested'], 'estimated_unit_cost' => $line['estimated_unit_cost']];
        }

        return $this->save->handle($propertyId, $departmentId, $rows, $userId, notes: __('From reorder levels'), source: RequisitionSource::Reorder);
    }
}
