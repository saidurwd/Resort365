<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Modules\Procurement\Enums\RfqStatus;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Jobs\SendVendorEmail;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\RfqVendor;
use Modules\Procurement\Models\VendorContact;

/**
 * Emails the RFQ to its vendors as a PDF (Step 5.5). A vendor with no email address is skipped and named in the result;
 * the RFQ is sent once at least one vendor got it. Quotations are entered as the answers come in.
 */
class SendRfq extends Action
{
    /**
     * @return array{rfq: Rfq, skipped: list<string>}
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(Rfq $rfq): array
    {
        $sent = [];
        $skipped = [];

        $locked = $this->transaction(function () use ($rfq, &$sent, &$skipped): Rfq {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if (! in_array($locked->status, [RfqStatus::Draft, RfqStatus::Sent], true)) {
                throw new ProcurementRuleViolated(__('RFQ :no is :status.', ['no' => $locked->rfq_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            foreach (RfqVendor::query()->where('rfq_id', $locked->id)->with('vendor')->get() as $row) {
                $address = VendorContact::query()->where('vendor_id', $row->vendor_id)->where('is_primary', true)->whereNotNull('email')->value('email') ?? $row->vendor->email;

                if ($address === null || $address === '') {
                    $skipped[] = $row->vendor->name;

                    continue;
                }

                $row->forceFill(['sent_at' => now()])->save();
                $sent[] = [$row->vendor_id, (string) $address];
            }

            if ($sent === []) {
                throw new ProcurementRuleViolated(__('None of the vendors has an email address.'));
            }

            $locked->forceFill(['status' => RfqStatus::Sent, 'sent_at' => $locked->sent_at ?? now()])->save();

            return $locked;
        });

        foreach ($sent as [$vendorId, $address]) {
            SendVendorEmail::dispatch('rfq', $locked->id, $address, $vendorId);
        }

        return ['rfq' => $locked, 'skipped' => $skipped];
    }
}
