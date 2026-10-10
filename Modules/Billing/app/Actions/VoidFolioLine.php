<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Events\FolioChargeVoided;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\FolioLedger;

/**
 * Voids a charge or adjustment with a reason (ARCHITECTURE §5.9): the line stays on the folio,
 * marked void, and no longer counts in the balance. Payments are reversed by refunds, not voids.
 */
class VoidFolioLine extends Action
{
    public function __construct(private readonly FolioLedger $ledger) {}

    /**
     * @throws ChargeRejected
     */
    public function handle(FolioLine $line, string $reason, ?int $userId = null): FolioLine
    {
        return $this->transaction(function () use ($line, $reason, $userId): FolioLine {
            $folio = Folio::query()->lockForUpdate()->findOrFail($line->folio_id);
            $locked = FolioLine::query()->lockForUpdate()->findOrFail($line->id);

            if (! $folio->isOpen()) {
                throw new ChargeRejected(__('Folio :no is :status.', ['no' => $folio->folio_no, 'status' => strtolower($folio->status->label())]));
            }

            if ($locked->is_voided) {
                throw new ChargeRejected(__('This line is already void.'));
            }

            if (in_array($locked->line_type, [FolioLineType::Payment, FolioLineType::Refund], true)) {
                throw new ChargeRejected(__('Payments are not voided here; they are reversed with a refund.'));
            }

            $locked->forceFill(['is_voided' => true, 'voided_by' => $userId, 'voided_at' => now(), 'void_reason' => $reason])->save();
            $this->ledger->recalculate($folio);

            FolioChargeVoided::dispatch($locked->tenant_id, $locked->id, $locked->property_id);

            return $locked;
        });
    }
}
