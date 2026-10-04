<?php

/*
| PreventiveCalendar: when a preventive schedule is due and its next date.
*/

use Modules\Housekeeping\Services\PreventiveCalendar;

it('is due on and after its date', function (): void {
    $calendar = new PreventiveCalendar;

    expect($calendar->isDue('2026-11-10', '2026-11-09'))->toBeFalse()
        ->and($calendar->isDue('2026-11-10', '2026-11-10'))->toBeTrue()
        ->and($calendar->isDue('2026-11-10', '2026-12-01'))->toBeTrue();
});

it('moves on by one interval when on time', function (): void {
    expect((new PreventiveCalendar)->following('2026-11-10', 90, '2026-11-10'))->toBe('2027-02-08');
});

it('skips missed intervals so one work order is opened, not one per interval', function (): void {
    // Due 1 Jan every 30 days; noticed on 15 Mar: next is the first date after 15 Mar.
    expect((new PreventiveCalendar)->following('2026-01-01', 30, '2026-03-15'))->toBe('2026-04-01');
});
