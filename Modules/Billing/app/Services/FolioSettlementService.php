<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Actions\IssueInvoice;
use Modules\Billing\Actions\PostRoomNights;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\DTOs\FolioSummary;
use Modules\Billing\DTOs\InvoiceSummary;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;

class FolioSettlementService implements FolioSettlement
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly PostRoomNights $postNights,
        private readonly IssueInvoice $issue,
    ) {}

    public function folios(int $reservationId): array
    {
        $this->ledger->guestFolio($reservationId);
        $withCharges = FolioLine::query()->whereIn('line_type', [FolioLineType::Charge->value, FolioLineType::Adjustment->value])->where('is_voided', false)
            ->whereIn('folio_id', Folio::query()->where('reservation_id', $reservationId)->select('id'))->distinct()->pluck('folio_id')->all();

        return Folio::query()->where('reservation_id', $reservationId)->orderBy('id')->get()->map(fn (Folio $folio): FolioSummary => new FolioSummary(
            $folio->id, $folio->folio_no, $folio->type, $folio->name, $folio->status, $folio->bill_to_type, $folio->bill_to_id, $folio->currency_code,
            $folio->balance, in_array($folio->id, $withCharges, true),
        ))->values()->all();
    }

    public function postRoomNights(int $reservationId, array $nights, ?int $userId = null): array
    {
        return $this->postNights->handle($reservationId, $nights, $userId);
    }

    public function isSettled(int $reservationId): bool
    {
        return ! Folio::query()->where('reservation_id', $reservationId)->where('status', FolioStatus::Open->value)->where('balance', '!=', 0)->exists();
    }

    public function invoiceAndClose(int $reservationId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($reservationId, $userId): array {
            $invoices = [];

            foreach (Folio::query()->where('reservation_id', $reservationId)->where('status', FolioStatus::Open->value)->lockForUpdate()->orderBy('id')->get() as $folio) {
                if (! BigDecimal::of($folio->balance)->isZero()) {
                    throw new ChargeRejected(__('Folio :no still has a balance of :balance.', ['no' => $folio->folio_no, 'balance' => $folio->balance]));
                }

                $hasCharges = FolioLine::query()->where('folio_id', $folio->id)->whereIn('line_type', [FolioLineType::Charge->value, FolioLineType::Adjustment->value])->where('is_voided', false)->exists();

                if ($hasCharges) {
                    $invoice = $this->issue->handle($folio, $userId);
                    $invoices[] = new InvoiceSummary($invoice->id, $invoice->invoice_no, $folio->id, $invoice->total, $invoice->paid, $invoice->on_account);
                }

                $folio->forceFill(['status' => $hasCharges ? FolioStatus::Settled : FolioStatus::Closed])->save();
            }

            return $invoices;
        }, 3);
    }
}
