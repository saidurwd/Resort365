<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\FolioLedger;
use Modules\Billing\Services\FolioMath;
use Modules\Billing\Services\TaxSplitter;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\DTOs\TaxBreakdown;
use Modules\Core\DTOs\TaxLine;
use Modules\Core\Enums\TaxType;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * Posts a charge to a reservation's folio (ARCHITECTURE §5.9): taxes from the charge code's tax
 * category (TaxEngine), the folio from the routing rules unless one is given, the posting date
 * from the property's business date. Staff may post to any booking that is not over or cancelled
 * (e.g. an airport pickup before arrival); other modules go through FolioPostingContract, which
 * also requires the guest to be checked in and the credit limit to hold.
 */
class PostCharge extends Action
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly FolioMath $math,
        private readonly TaxEngine $taxes,
        private readonly Settings $settings,
        private readonly GuestLookup $guests,
        private readonly TaxSplitter $splitter,
    ) {}

    /**
     * @throws ChargeRejected
     */
    public function handle(FolioCharge $charge, bool $requireInHouse = false, bool $enforceCreditLimit = false): FolioLine
    {
        $reservation = $this->ledger->reservation($charge->reservationId);

        if ($requireInHouse && $reservation->status !== ReservationStatus::CheckedIn) {
            throw new ChargeRejected(__('Booking :code is not checked in, so it cannot be charged.', ['code' => $reservation->code]));
        }

        if (! in_array($reservation->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
            throw new ChargeRejected(__('Booking :code is :status and takes no more charges.', ['code' => $reservation->code, 'status' => strtolower($reservation->status->label())]));
        }

        $code = ChargeCode::query()->whereKey($charge->chargeCodeId)->where('is_active', true)->first()
            ?? throw new ChargeRejected(__('Unknown or inactive charge code.'));

        if ($charge->quantity < 1 || BigDecimal::of($charge->unitPrice)->isNegative()) {
            throw new ChargeRejected(__('Quantity must be at least 1 and the price may not be negative.'));
        }

        if ($charge->outletCharge && $reservation->noRoomCharges) {
            throw new ChargeRejected(__('Booking :code takes no room charges from outlets.', ['code' => $reservation->code]));
        }

        // TaxEngine taxes the line amount; quantity only counts units for fixed taxes. Taxes worked out by
        // the source (a restaurant bill) are posted as they are.
        $tax = $charge->taxLines !== null
            ? $this->presetTaxes((string) BigDecimal::of($charge->unitPrice)->multipliedBy($charge->quantity)->toScale(2), $charge->taxLines)
            : $this->taxes->calculate((string) BigDecimal::of($charge->unitPrice)->multipliedBy($charge->quantity)->toScale(2), $code->tax_category_id, $charge->priceIncludesTax, $charge->quantity);

        return $this->transaction(function () use ($charge, $reservation, $code, $tax, $enforceCreditLimit): FolioLine {
            $routed = $this->ledger->folioFor($reservation->id, $code->category);
            $target = $charge->folioId !== null ? Folio::query()->where('reservation_id', $reservation->id)->find($charge->folioId) : $routed;

            if (! $target instanceof Folio) {
                throw new ChargeRejected(__('That folio does not belong to this booking.'));
            }

            $folio = Folio::query()->lockForUpdate()->findOrFail($target->id);

            if (! $folio->isOpen()) {
                throw new ChargeRejected(__('Folio :no is :status.', ['no' => $folio->folio_no, 'status' => strtolower($folio->status->label())]));
            }

            if ($enforceCreditLimit && ! $this->math->withinCreditLimit($folio->balance, $tax->gross, $this->creditLimit($folio))) {
                throw new ChargeRejected(__('Folio :no would go over its credit limit of :limit.', ['no' => $folio->folio_no, 'limit' => $this->creditLimit($folio)]));
            }

            $guestFolioId = $charge->folioId === null ? $this->ledger->guestFolio($reservation->id)->id : null;

            $line = FolioLine::query()->create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'posting_date' => $this->ledger->businessDate($folio->property_id),
                'line_type' => FolioLineType::Charge,
                'charge_code_id' => $code->id,
                'extra_service_id' => $charge->extraServiceId,
                'description' => $charge->description !== null && $charge->description !== '' ? $charge->description : $code->name,
                'quantity' => (string) $charge->quantity,
                'unit_price' => $charge->unitPrice,
                'amount' => $tax->net,
                'tax_amount' => $tax->taxTotal,
                'tax_lines' => $this->breakdown($tax->taxes),
                'total' => $tax->gross,
                'reference_type' => $charge->referenceType,
                'reference_id' => $charge->referenceId,
                'revenue_posted_by_source' => $charge->revenuePostedBySource,
                'routed_from_folio_id' => $guestFolioId !== null && $guestFolioId !== $folio->id ? $guestFolioId : null,
                'posted_by' => $charge->postedBy,
            ]);

            $this->ledger->recalculate($folio);

            return $line;
        }, attempts: 3);
    }

    /**
     * A breakdown of taxes the source already worked out: net + each tax = gross.
     *
     * @param  array<string, string>  $taxLines  name => amount
     */
    private function presetTaxes(string $net, array $taxLines): TaxBreakdown
    {
        $lines = [];

        foreach ($taxLines as $name => $amount) {
            $lines[] = new TaxLine((string) $name, (string) $name, TaxType::Percent, '0', (string) BigDecimal::of($amount)->toScale(2));
        }

        $total = array_reduce($lines, fn (BigDecimal $sum, TaxLine $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero());

        return new TaxBreakdown($net, $lines, (string) $total->toScale(2), (string) BigDecimal::of($net)->plus($total)->toScale(2));
    }

    /**
     * Tax name => amount (taxes with the same name are added together).
     *
     * @param  list<TaxLine>  $taxes
     * @return array<string, string>
     */
    private function breakdown(array $taxes): array
    {
        return $this->splitter->sum(array_map(fn (TaxLine $line): array => [$line->name => $line->amount], $taxes));
    }

    /**
     * A company or travel-agent folio uses its account's credit limit; a guest folio the
     * property's billing.guest_credit_limit (0 = none).
     */
    public function creditLimit(Folio $folio): ?string
    {
        return match ($folio->bill_to_type) {
            BillTo::Company => $folio->bill_to_id !== null ? $this->guests->findCompany($folio->bill_to_id)?->creditLimit : null,
            BillTo::TravelAgent => $folio->bill_to_id !== null ? $this->guests->findTravelAgent($folio->bill_to_id)?->creditLimit : null,
            BillTo::Guest => (string) $this->settings->get('billing.guest_credit_limit', $folio->property_id),
        };
    }
}
