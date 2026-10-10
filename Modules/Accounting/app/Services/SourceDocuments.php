<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\JournalEntry;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Reservation\Contracts\ReservationLookup;

/**
 * The documents an automatic or voucher entry came from (Step 4.5), for the drill-down from a report line:
 * the receipt, invoice, bill, voucher or transfer, and the booking behind it. A night sweep lists the
 * folio charges of that day, each linked to its booking. Facts come through the modules' contracts.
 */
class SourceDocuments
{
    public function __construct(
        private readonly LedgerFacts $facts,
        private readonly ReservationLookup $reservations,
    ) {}

    /**
     * @return list<array{label: string, url: string, detail: string|null}>
     */
    public function for(JournalEntry $entry): array
    {
        $id = (int) $entry->source_id;
        $links = [];

        switch ($entry->source_type) {
            case 'payment':
                $payment = $this->facts->payment($id);
                $links[] = ['label' => (string) __('Payment receipt :no', ['no' => $payment->receiptNo ?? '#'.$id]), 'url' => route('billing.payments.receipt', $id), 'detail' => null];
                $links = [...$links, ...$this->booking($payment->reservationId ?? null)];
                break;
            case 'invoice':
                $invoice = $this->facts->invoice($id);
                $links[] = ['label' => (string) __('Invoice :no', ['no' => $invoice->invoiceNo ?? '#'.$id]), 'url' => route('billing.invoices.pdf', $id), 'detail' => null];
                $links = [...$links, ...$this->booking($invoice->reservationId ?? null)];
                break;
            case 'city_ledger_entry':
                $links = [...$links, ...$this->booking($this->facts->cityLedgerTransfer($id)->reservationId ?? null)];
                $links[] = ['label' => (string) __('City ledger'), 'url' => route('billing.city-ledger.index'), 'detail' => null];
                break;
            case 'reservation':
                $links = [...$links, ...$this->booking($id)];
                break;
            case 'folio_line':
                $links = [...$links, ...$this->booking($this->facts->charge($id)->reservationId ?? null)];
                break;
            case 'pos_bill':
                $links[] = ['label' => (string) __('Restaurant bill receipt'), 'url' => route('restaurant.bills.receipt', $id), 'detail' => null];
                break;
            case 'pos_session':
                $links[] = ['label' => (string) __('POS session report'), 'url' => route('restaurant.sessions.report', $id), 'detail' => null];
                break;
            case 'voucher':
                $links[] = ['label' => (string) __('Voucher'), 'url' => route('accounting.vouchers.show', $id), 'detail' => null];
                break;
            case 'fund_transfer':
                $links[] = ['label' => (string) __('Transfer'), 'url' => route('accounting.transfers.show', $id), 'detail' => null];
                break;
            case 'property_day':
                $date = substr((string) $entry->source_event, strlen('revenue:'));
                $links[] = ['label' => (string) __('Flash report of :date', ['date' => $date]), 'url' => route('frontoffice.reports.flash', ['date' => $date]), 'detail' => null];

                foreach (array_slice($this->facts->chargesOn($id, $date), 0, 200) as $charge) {
                    if ($charge->reservationId !== null) {
                        $links = [...$links, ...$this->booking($charge->reservationId, ($charge->chargeCode ?? __('Charge')).' · '.number_format((float) $charge->amount, 2))];
                    }
                }

                break;
        }

        return $links;
    }

    /**
     * @return list<array{label: string, url: string, detail: string|null}>
     */
    private function booking(?int $reservationId, ?string $detail = null): array
    {
        if ($reservationId === null) {
            return [];
        }

        $reservation = $this->reservations->find($reservationId);

        return [['label' => (string) __('Booking :code', ['code' => $reservation->code ?? '#'.$reservationId]), 'url' => route('reservation.bookings.show', $reservationId), 'detail' => $detail]];
    }
}
