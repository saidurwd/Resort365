<?php

namespace Modules\FrontOffice\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Billing\Contracts\DailyTakings;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;
use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\FrontOffice\Exceptions\NightAuditNotPossible;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\FrontOffice\Models\NightAudit;
use Modules\FrontOffice\Services\DailyStatsCalculator;
use Modules\FrontOffice\Services\NightAuditChecks;
use Modules\Property\Contracts\BusinessDates;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Throwable;

/**
 * The night audit of a property's business date (ARCHITECTURE §5.7), from the wizard or the
 * scheduler:
 *
 * 1. checks (NightAuditChecks): departures still in house block the audit;
 * 2. posts each in-house stay's room nights up to the date to its folios (nightly revenue
 *    recognition; at_checkout leaves them for check-out);
 * 3. marks expected arrivals not checked in as no-shows;
 * 4. releases expired tentative holds;
 * 5. the restaurant check (TODO(step-3.7): POS sessions closed, no open bills);
 * 6. reports the package split (meal component of the nights posted, kept on the folio lines);
 * 7. snapshots the day's statistics;
 * 8. moves the business date on by one day.
 *
 * Steps 2–8 run in one transaction. The audit row is unique per property and date, so a completed
 * date cannot be audited again; a blocked or failed audit can be run again.
 */
class RunNightAudit extends Action
{
    public function __construct(
        private readonly NightAuditChecks $checks,
        private readonly StayOperations $stays,
        private readonly ReservationLookup $reservations,
        private readonly FolioSettlement $settlement,
        private readonly DailyTakings $takings,
        private readonly InventoryCatalog $catalog,
        private readonly BusinessDates $businessDates,
        private readonly DailyStatsCalculator $stats,
    ) {}

    /**
     * @return NightAudit completed, blocked or failed
     *
     * @throws NightAuditNotPossible
     */
    public function handle(int $propertyId, NightAuditTrigger $trigger = NightAuditTrigger::Manual, ?int $userId = null): NightAudit
    {
        $preview = $this->checks->preview($propertyId);

        if ($preview['notPossible'] !== null) {
            throw new NightAuditNotPossible($preview['notPossible']);
        }

        $date = $preview['date'];
        $audit = $this->claim($propertyId, $date, $trigger, $userId);

        if ($preview['blocking'] !== []) {
            $audit->forceFill(['status' => NightAuditStatus::Blocked, 'issues' => $preview['blocking'], 'completed_at' => null])->save();

            return $audit;
        }

        try {
            return $this->transaction(fn (): NightAudit => $this->run($audit, $preview['inHouse'], $preview['noShows'], $preview['nightly'], $preview['warnings'], $userId));
        } catch (Throwable $exception) {
            report($exception);
            $audit->forceFill(['status' => NightAuditStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 1000)])->save();

            return $audit;
        }
    }

    /**
     * Takes the audit row for the date: a new one, or a blocked/failed one run again.
     *
     * @throws NightAuditNotPossible
     */
    private function claim(int $propertyId, string $date, NightAuditTrigger $trigger, ?int $userId): NightAudit
    {
        $fresh = ['status' => NightAuditStatus::Running, 'trigger' => $trigger, 'started_by' => $userId, 'started_at' => now(), 'issues' => null, 'error' => null];
        $existing = NightAudit::query()->where('property_id', $propertyId)->where('business_date', $date)->first();

        if ($existing instanceof NightAudit) {
            // Only the caller that moves it out of a retryable state may run it.
            $claimed = NightAudit::query()->whereKey($existing->id)
                ->whereIn('status', [NightAuditStatus::Blocked->value, NightAuditStatus::Failed->value])->update(['status' => NightAuditStatus::Running->value]);

            if ($claimed !== 1) {
                throw new NightAuditNotPossible($existing->status === NightAuditStatus::Completed
                    ? __('The night audit of :date is already done.', ['date' => CarbonImmutable::parse($date)->format('d M Y')])
                    : __('The night audit of :date is running.', ['date' => CarbonImmutable::parse($date)->format('d M Y')]));
            }

            $existing->forceFill($fresh)->save();

            return $existing;
        }

        try {
            return NightAudit::query()->create(['property_id' => $propertyId, 'business_date' => $date, ...$fresh]);
        } catch (UniqueConstraintViolationException) {
            throw new NightAuditNotPossible(__('The night audit of :date is running.', ['date' => CarbonImmutable::parse($date)->format('d M Y')]));
        }
    }

    /**
     * @param  list<ReservationSummary>  $inHouse
     * @param  list<ReservationSummary>  $noShows
     * @param  list<string>  $warnings
     */
    private function run(NightAudit $audit, array $inHouse, array $noShows, bool $nightly, array $warnings, ?int $userId): NightAudit
    {
        $propertyId = $audit->property_id;
        $date = $audit->business_date->toDateString();
        $steps = [['step' => 'verify', 'result' => $warnings === [] ? __('All departures are checked out.') : __('All departures are checked out.').' '.implode(' ', $warnings)]];

        // 2. Room and tax charges for each in-house night up to the business date.
        $posted = 0;
        $rejected = [];

        if ($nightly) {
            foreach ($inHouse as $stay) {
                try {
                    $ids = $this->settlement->postRoomNights($stay->id, $this->stays->unpostedNights($stay->id, $date), $userId);
                    $this->stays->markNightsPosted($ids);
                    $posted += count($ids);
                } catch (ChargeRejected $exception) {
                    $rejected[] = $stay->code.': '.$exception->getMessage();
                }
            }
        }

        $steps[] = ['step' => 'post_room_charges', 'result' => $nightly
            ? trans_choice('Posted :count room night|Posted :count room nights', $posted).' '.trans_choice('for :count stay in house.|for :count stays in house.', count($inHouse))
                .($rejected !== [] ? ' '.__('Not posted (left for check-out): :list.', ['list' => implode('; ', $rejected)]) : '')
            : __('Room revenue is recognised at check-out: nothing posted.')];

        // 3. No-shows.
        foreach ($noShows as $arrival) {
            $this->stays->markNoShow($arrival->id, $date, $userId);
        }

        $steps[] = ['step' => 'no_shows', 'result' => $noShows === [] ? __('No no-shows.')
            : trans_choice(':count no-show: :list.|:count no-shows: :list.', count($noShows), ['list' => implode(', ', array_map(fn (ReservationSummary $arrival): string => $arrival->code, $noShows))])];

        // 4. Expired tentative holds.
        $released = $this->stays->expireHolds($propertyId);
        $steps[] = ['step' => 'expired_holds', 'result' => trans_choice(':count expired hold released.|:count expired holds released.', $released)];

        // 5. TODO(step-3.7): refuse the audit while restaurant POS sessions or bills are open.
        $steps[] = ['step' => 'restaurant', 'result' => __('Restaurant check: comes with the restaurant POS.')];

        // 6–7. Package split and the day's statistics.
        $takings = $this->takings->forDate($propertyId, $date);
        $night = $this->reservations->occupancy($propertyId, $date);
        $roomsTotal = count(array_filter($this->catalog->rooms($propertyId), fn (RoomSummary $room): bool => $room->isActive));
        $figures = $this->stats->calculate($roomsTotal, $night->roomsOutOfOrder, $night->roomsOccupied, $night->roomRevenue);

        $steps[] = ['step' => 'package_split', 'result' => __('Meal component of the nights: :meals F&B, :room room revenue.', ['meals' => $night->packageMealRevenue, 'room' => $night->roomRevenue])];

        DailyStatistic::query()->create([
            'property_id' => $propertyId, 'business_date' => $date, 'rooms_total' => $roomsTotal, 'rooms_out_of_order' => $night->roomsOutOfOrder,
            'rooms_blocked' => $night->roomsBlocked, 'rooms_occupied' => $night->roomsOccupied, ...$figures,
            'room_revenue' => $night->roomRevenue, 'package_meal_revenue' => $night->packageMealRevenue, 'room_tax' => $night->roomTax,
            'charges_total' => $takings->chargesTotal, 'received_total' => $takings->receivedTotal, 'refunded_total' => $takings->refundedTotal,
            'adults' => $night->adults, 'children' => $night->children, 'arrivals' => $night->arrivals, 'departures' => $night->departures,
            'no_shows' => $night->noShows, 'takings' => $takings->toArray(),
        ]);
        $steps[] = ['step' => 'statistics', 'result' => __(':occupied of :available rooms sold (:percent%), ADR :adr, RevPAR :revpar.', [
            'occupied' => $night->roomsOccupied, 'available' => $figures['rooms_available'], 'percent' => $figures['occupancy_percent'], 'adr' => $figures['adr'], 'revpar' => $figures['revpar'],
        ])];

        // 8. The next business date.
        $next = $this->businessDates->advance($propertyId, $date);
        $steps[] = ['step' => 'business_date', 'result' => __('Business date is now :date.', ['date' => CarbonImmutable::parse($next)->format('d M Y')])];

        $audit->forceFill([
            'status' => NightAuditStatus::Completed, 'completed_at' => now(), 'nights_posted' => $posted, 'no_shows' => count($noShows),
            'holds_released' => $released, 'steps' => $steps, 'issues' => null, 'error' => null,
        ])->save();

        NightAuditCompleted::dispatch($audit->tenant_id, $propertyId, $date, $next);

        return $audit;
    }
}
