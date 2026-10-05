<?php

namespace Modules\FrontOffice\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Billing\Contracts\DailyTakings;
use Modules\Core\Contracts\Settings;
use Modules\FrontOffice\Contracts\NightAuditBlockers;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Models\NightAudit;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * What the night audit of a property's business date will find (ARCHITECTURE §5.7 step 1), for the
 * wizard and for the audit itself: departures still in house block it; expected arrivals not
 * checked in will be no-shows; rooms of a group not arrived yet are left as they are (a warning);
 * the in-house stays and their nights to post; open cashier shifts (a warning); and whatever other
 * modules register (NightAuditBlockers, e.g. the restaurant's open POS sessions).
 */
class NightAuditChecks
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly ReservationLookup $reservations,
        private readonly StayOperations $stays,
        private readonly DailyTakings $takings,
        private readonly Settings $settings,
        private readonly NightAuditSchedule $schedule,
        private readonly NightAuditBlockers $blockers,
    ) {}

    /**
     * @return array{property: PropertySummary, date: string, nightly: bool, departures: list<ReservationSummary>, noShows: list<ReservationSummary>,
     *     partialGroups: list<ReservationSummary>, inHouse: list<ReservationSummary>, nightsToPost: int, openShifts: int, blocking: list<string>,
     *     warnings: list<string>, notPossible: string|null, audit: NightAudit|null}
     */
    public function preview(int $propertyId): array
    {
        $property = $this->properties->find($propertyId) ?? throw new InvalidArgumentException('Unknown property.');
        $date = $property->businessDate;
        $nightly = $this->settings->get('billing.revenue_recognition') !== 'at_checkout';
        $inHouse = $this->reservations->inHouse($propertyId);
        $departures = $this->reservations->departures($propertyId, $date);
        $partialGroups = array_values(array_filter($inHouse, fn (ReservationSummary $stay): bool => $stay->itemsCheckedIn < $stay->itemsTotal && $stay->checkIn <= $date));
        $nightsToPost = $nightly ? array_sum(array_map(fn (ReservationSummary $stay): int => count($this->stays->unpostedNights($stay->id, $date)), $inHouse)) : 0;
        $openShifts = $this->takings->forDate($propertyId, $date)->openShifts;

        $blocking = [...array_map(fn (ReservationSummary $stay): string => __(':code (:guest) was due to leave on :date and is still in house: check out or extend the stay.', [
            'code' => $stay->code, 'guest' => $stay->groupName ?? $stay->guestName, 'date' => CarbonImmutable::parse($stay->checkOut)->format('d M'),
        ]), $departures), ...$this->blockers->blocking($propertyId, $date)];
        $warnings = [
            ...array_map(fn (ReservationSummary $stay): string => __(':code: :in of :total rooms checked in; the others stay booked.', [
                'code' => $stay->code, 'in' => $stay->itemsCheckedIn, 'total' => $stay->itemsTotal]), $partialGroups),
            ...($openShifts > 0 ? [trans_choice(':count cashier shift is still open.|:count cashier shifts are still open.', $openShifts)] : []),
        ];

        return [
            'property' => $property,
            'date' => $date,
            'nightly' => $nightly,
            'departures' => $departures,
            'noShows' => array_values(array_filter($this->reservations->arrivals($propertyId, $date),
                fn (ReservationSummary $arrival): bool => in_array($arrival->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed], true))),
            'partialGroups' => $partialGroups,
            'inHouse' => $inHouse,
            'nightsToPost' => $nightsToPost,
            'openShifts' => $openShifts,
            'blocking' => $blocking,
            'warnings' => $warnings,
            'notPossible' => $this->notPossible($property),
            'audit' => NightAudit::query()->where('property_id', $propertyId)->where('business_date', $date)->first(),
        ];
    }

    /**
     * Why the audit cannot run at all now, if so.
     */
    public function notPossible(PropertySummary $property): ?string
    {
        $audit = NightAudit::query()->where('property_id', $property->id)->where('business_date', $property->businessDate)->first();

        if ($audit instanceof NightAudit && $audit->status === NightAuditStatus::Completed) {
            return __('The night audit of :date is already done.', ['date' => CarbonImmutable::parse($property->businessDate)->format('d M Y')]);
        }

        if (! $this->schedule->mayRun($property->businessDate, $property->timezone, CarbonImmutable::now())) {
            return __('The business date :date is already ahead of the calendar: the next audit can run on that day.', ['date' => CarbonImmutable::parse($property->businessDate)->format('d M Y')]);
        }

        return null;
    }
}
