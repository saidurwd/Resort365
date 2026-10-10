<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\PostingService;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Restaurant\Contracts\RestaurantFacts;

/**
 * Posts the events that happened before Accounting was running, or that it could not post (no open period,
 * an unmapped account): payments and refunds, cancellation fees, city ledger transfers, invoices and the
 * charges of every closed business date. Safe to run again, because every posting is idempotent.
 */
class BackpostHistory extends Action
{
    public function __construct(
        private readonly LedgerFacts $facts,
        private readonly ReservationLookup $reservations,
        private readonly PropertyDirectory $properties,
        private readonly PostingService $posting,
        private readonly RestaurantFacts $restaurant,
    ) {}

    /**
     * @return array{posted: int, failed: list<string>}
     */
    public function handle(): array
    {
        $history = $this->facts->history();
        $posted = 0;
        $failed = [];
        $run = function (string $what, callable $post) use (&$posted, &$failed): void {
            try {
                if ($post() instanceof JournalEntry) {
                    $posted++;
                }
            } catch (AccountingRuleViolated $exception) {
                $failed[] = $what.': '.$exception->getMessage();
            }
        };

        foreach ($history['payments'] as $id) {
            $run(__('Payment #:id', ['id' => $id]), fn (): ?JournalEntry => $this->posting->payment($id));
        }

        foreach ($this->reservations->cancellations() as $cancellation) {
            $run(__('Cancellation of :code', ['code' => $cancellation->code]), fn (): ?JournalEntry => $this->posting->cancellationFee($cancellation->reservationId));
        }

        foreach ($history['transfers'] as $id) {
            $run(__('City ledger transfer #:id', ['id' => $id]), fn (): ?JournalEntry => $this->posting->cityLedgerTransfer($id));
        }

        foreach ($history['invoices'] as $id) {
            $run(__('Invoice #:id', ['id' => $id]), fn (): ?JournalEntry => $this->posting->invoice($id));
        }

        $restaurant = $this->restaurant->history();

        foreach ($restaurant['bills'] as $id) {
            $run(__('Restaurant bill #:id', ['id' => $id]), function () use ($id): ?JournalEntry {
                $entry = $this->posting->restaurantBill($id);

                return $this->restaurant->bill($id)?->voided === true ? $this->posting->restaurantBillVoided($id) : $entry;
            });
        }

        foreach ($restaurant['sessions'] as $id) {
            $run(__('POS session #:id', ['id' => $id]), fn (): ?JournalEntry => $this->posting->sessionVariance($id));
        }

        // A date is posted once its night audit has closed it: the property's business date is still open.
        foreach ($history['charge_dates'] as $day) {
            $open = $this->properties->find($day['property_id'])->businessDate ?? null;

            if ($open !== null && $day['date'] < $open) {
                $run(__('Charges of :date', ['date' => $day['date']]), fn (): ?JournalEntry => $this->posting->nightRevenue($day['property_id'], $day['date']));
            }
        }

        return ['posted' => $posted, 'failed' => $failed];
    }
}
