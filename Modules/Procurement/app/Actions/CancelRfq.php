<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Rfq;

class CancelRfq extends Action
{
    /**
     * @throws ProcurementRuleViolated
     */
    public function handle(Rfq $rfq): Rfq
    {
        return $this->transaction(function () use ($rfq): Rfq {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if (! in_array($locked->status, [RfqStatus::Draft, RfqStatus::Sent], true)) {
                throw new ProcurementRuleViolated(__('RFQ :no is :status.', ['no' => $locked->rfq_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $locked->forceFill(['status' => RfqStatus::Cancelled])->save();

            return $locked;
        });
    }
}
