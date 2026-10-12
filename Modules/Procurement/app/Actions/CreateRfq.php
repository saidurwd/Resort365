<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Procurement\Enums\PurchaseRequisitionStatus;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\RfqVendor;
use Modules\Procurement\Models\Vendor;

/**
 * Prepares a request for quotation from an approved purchase requisition, for two or more vendors (Step 5.5).
 */
class CreateRfq extends Action
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    /**
     * @param  list<int>  $vendorIds
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(PurchaseRequisition $requisition, array $vendorIds, int $userId, ?string $dueOn = null, ?string $notes = null): Rfq
    {
        $vendorIds = array_values(array_unique($vendorIds));

        if (count($vendorIds) < 2) {
            throw new ProcurementRuleViolated(__('Choose at least two vendors to compare.'));
        }

        if (Vendor::query()->whereIn('id', $vendorIds)->where('status', VendorStatus::Active->value)->count() !== count($vendorIds)) {
            throw new ProcurementRuleViolated(__('Every vendor must be an active vendor of this company.'));
        }

        return $this->transaction(function () use ($requisition, $vendorIds, $userId, $dueOn, $notes): Rfq {
            $locked = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($locked->status !== PurchaseRequisitionStatus::Approved) {
                throw new ProcurementRuleViolated(__('Requisition :no is :status: only an approved requisition goes to RFQ.', ['no' => $locked->requisition_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $rfq = Rfq::query()->create([
                'property_id' => $locked->property_id, 'rfq_no' => $this->numbers->next('rfq'), 'purchase_requisition_id' => $locked->id, 'status' => RfqStatus::Draft,
                'due_on' => $dueOn, 'notes' => $notes, 'created_by' => $userId,
            ]);

            foreach ($vendorIds as $vendorId) {
                RfqVendor::query()->create(['property_id' => $locked->property_id, 'rfq_id' => $rfq->id, 'vendor_id' => $vendorId]);
            }

            return $rfq->load('vendors');
        }, attempts: 3);
    }
}
