<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\StockIssuing;
use Modules\Inventory\DTOs\NewStockIssue;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\StockRequisitionLine;

/**
 * The store keeper issues an approved requisition (Step 5.4): up to the requested quantity of each item (all of it
 * unless told otherwise), at the store's average cost, charged to the requesting department. A partial issue closes it.
 */
class IssueRequisition extends Action
{
    public function __construct(private readonly StockIssuing $issuing) {}

    /**
     * @param  array<int, string>  $quantities  item id => quantity to issue (default: the requested quantity)
     *
     * @throws StockRuleViolated
     */
    public function handle(StockRequisition $requisition, array $quantities = [], ?int $userId = null): StockRequisition
    {
        return $this->transaction(function () use ($requisition, $quantities, $userId): StockRequisition {
            $locked = StockRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($locked->status !== RequisitionStatus::Approved) {
                throw new StockRuleViolated(__('Requisition :no is :status: only an approved requisition is issued.', ['no' => $locked->requisition_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $lines = StockRequisitionLine::query()->where('stock_requisition_id', $locked->id)->get();
            $issue = [];

            foreach ($lines as $line) {
                $quantity = BigDecimal::of($quantities[$line->item_id] ?? (string) $line->quantity);

                if ($quantity->isGreaterThan($line->quantity) || $quantity->isNegative()) {
                    throw new StockRuleViolated(__('Issue between nothing and the :asked requested.', ['asked' => rtrim(rtrim((string) $line->quantity, '0'), '.')]));
                }

                if ($quantity->isPositive()) {
                    $issue[] = ['item_id' => $line->item_id, 'quantity' => (string) $quantity];
                }
            }

            if ($issue === []) {
                throw new StockRuleViolated(__('Issue at least one item.'));
            }

            $document = $this->issuing->issue(new NewStockIssue($locked->store_id, $locked->department_id, $issue, 'requisition', $locked->requisition_no, 'requisition', $locked->id, $locked->id, $userId));

            foreach ($lines as $line) {
                $line->forceFill(['issued_quantity' => collect($issue)->firstWhere('item_id', $line->item_id)['quantity'] ?? '0'])->save();
            }

            $locked->forceFill(['status' => RequisitionStatus::Issued, 'issued_at' => now(), 'issue_id' => $document->id])->save();

            return $locked->load('lines');
        }, attempts: 3);
    }
}
