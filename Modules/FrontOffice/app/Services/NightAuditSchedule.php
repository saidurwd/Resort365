<?php

namespace Modules\FrontOffice\Services;

use Carbon\CarbonImmutable;

/**
 * When a property's night audit is due (ARCHITECTURE §5.7, without the database). The audit time is
 * the property's local time (core.night_audit_time). A time before noon is in the small hours after
 * the business date (02:00 → 02:00 the next calendar day); a time from noon is on the business date
 * itself (23:30 → 23:30 that day). An audit never runs for a business date later than the
 * property's calendar date, so the business date stays at most one day ahead of the calendar.
 */
class NightAuditSchedule
{
    public function dueAt(string $businessDate, string $time, string $timezone): CarbonImmutable
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time.':0'));
        $day = CarbonImmutable::parse($businessDate, $timezone)->startOfDay();

        return ($hour < 12 ? $day->addDay() : $day)->setTime($hour, $minute);
    }

    public function isDue(string $businessDate, string $time, string $timezone, CarbonImmutable $now): bool
    {
        return $now->greaterThanOrEqualTo($this->dueAt($businessDate, $time, $timezone)) && $this->mayRun($businessDate, $timezone, $now);
    }

    /**
     * Whether the business date may be audited now: not while it is later than the local calendar date.
     */
    public function mayRun(string $businessDate, string $timezone, CarbonImmutable $now): bool
    {
        return $businessDate <= $now->setTimezone($timezone)->toDateString();
    }
}
