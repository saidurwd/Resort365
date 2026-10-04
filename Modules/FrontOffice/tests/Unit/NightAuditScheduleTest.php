<?php

/*
| NightAuditSchedule: when a property's night audit is due (local time, Asia/Dhaka = UTC+6).
*/

use Carbon\CarbonImmutable;
use Modules\FrontOffice\Services\NightAuditSchedule;

it('puts a small-hours audit time on the morning after the business date', function (): void {
    expect((new NightAuditSchedule)->dueAt('2026-11-10', '02:00', 'Asia/Dhaka')->toIso8601String())->toBe('2026-11-11T02:00:00+06:00');
});

it('puts an audit time from noon on the business date itself', function (): void {
    expect((new NightAuditSchedule)->dueAt('2026-11-10', '23:30', 'Asia/Dhaka')->toIso8601String())->toBe('2026-11-10T23:30:00+06:00');
});

it('is due from the audit time on', function (string $nowUtc, bool $due): void {
    expect((new NightAuditSchedule)->isDue('2026-11-10', '02:00', 'Asia/Dhaka', CarbonImmutable::parse($nowUtc, 'UTC')))->toBe($due);
})->with([
    'the evening before' => ['2026-11-10 17:00', false],
    '01:59 local' => ['2026-11-10 19:59', false],
    '02:00 local' => ['2026-11-10 20:00', true],
    'later that morning' => ['2026-11-11 04:00', true],
]);

it('never runs for a business date ahead of the calendar', function (): void {
    $schedule = new NightAuditSchedule;
    $evening = CarbonImmutable::parse('2026-11-10 17:00', 'UTC'); // 23:00 in Dhaka on 10 Nov

    expect($schedule->mayRun('2026-11-10', 'Asia/Dhaka', $evening))->toBeTrue()
        ->and($schedule->mayRun('2026-11-11', 'Asia/Dhaka', $evening))->toBeFalse()
        // An evening audit time: due at 23:30 on the business date, not before.
        ->and($schedule->isDue('2026-11-10', '23:30', 'Asia/Dhaka', $evening))->toBeFalse()
        ->and($schedule->isDue('2026-11-10', '23:30', 'Asia/Dhaka', CarbonImmutable::parse('2026-11-10 17:30', 'UTC')))->toBeTrue();
});
