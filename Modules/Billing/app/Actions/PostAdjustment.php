<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\FolioLedger;

/**
 * Corrects a folio with an adjustment line (ARCHITECTURE §5.9): a positive amount adds to what is
 * owed, a negative one is a credit (e.g. goodwill). The amount includes any tax. The reason is the
 * line's description.
 */
class PostAdjustment extends Action
{
    public function __construct(private readonly FolioLedger $ledger) {}

    /**
     * @throws ChargeRejected
     */
    public function handle(Folio $folio, string $amount, string $reason, ?int $chargeCodeId = null, ?int $userId = null): FolioLine
    {
        if (BigDecimal::of($amount)->isZero()) {
            throw new ChargeRejected(__('An adjustment needs an amount.'));
        }

        return $this->transaction(function () use ($folio, $amount, $reason, $chargeCodeId, $userId): FolioLine {
            $locked = Folio::query()->lockForUpdate()->findOrFail($folio->id);

            if (! $locked->isOpen()) {
                throw new ChargeRejected(__('Folio :no is :status.', ['no' => $locked->folio_no, 'status' => strtolower($locked->status->label())]));
            }

            $line = FolioLine::query()->create([
                'property_id' => $locked->property_id,
                'folio_id' => $locked->id,
                'posting_date' => $this->ledger->businessDate($locked->property_id),
                'line_type' => FolioLineType::Adjustment,
                'charge_code_id' => $chargeCodeId,
                'description' => $reason,
                'quantity' => '1',
                'unit_price' => $amount,
                'amount' => $amount,
                'tax_amount' => '0',
                'total' => $amount,
                'posted_by' => $userId,
            ]);

            $this->ledger->recalculate($locked);

            return $line;
        });
    }
}
