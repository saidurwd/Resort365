<?php

/*
| The attendance arithmetic (Step 6.2): late, early leave, overtime and worked minutes against a shift, night shifts,
| holidays and missing punches, and the grouping of biometric punches into days.
*/
use Modules\HR\DTOs\ShiftTimes;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Services\AttendanceCalculator;
use Modules\HR\Services\PunchGrouper;

function acDay(): ShiftTimes
{
    return new ShiftTimes('09:00', '17:00', breakMinutes: 30, graceMinutes: 10, overtimeAfterMinutes: 15);
}

function acNight(): ShiftTimes
{
    return new ShiftTimes('22:00', '06:00', breakMinutes: 30, graceMinutes: 10, overtimeAfterMinutes: 15);
}

it('is on time within the grace minutes, and counts every minute from the start once past them', function (): void {
    $calculator = new AttendanceCalculator;
    $onTime = $calculator->calculate(acDay(), '09:10', '17:00');
    $late = $calculator->calculate(acDay(), '09:11', '17:00');

    expect($onTime->status)->toBe(AttendanceStatus::Present)->and($onTime->lateMinutes)->toBe(0)->and($onTime->workedMinutes)->toBe(440)
        ->and($late->status)->toBe(AttendanceStatus::Late)->and($late->lateMinutes)->toBe(11)->and($late->workedMinutes)->toBe(439);
});

it('counts early leave past the grace minutes, and never negative minutes for coming early or leaving late', function (): void {
    $calculator = new AttendanceCalculator;
    $early = $calculator->calculate(acDay(), '08:50', '16:40');
    $grace = $calculator->calculate(acDay(), '09:00', '16:50');

    expect($early->earlyLeaveMinutes)->toBe(20)->and($early->lateMinutes)->toBe(0)->and($early->overtimeMinutes)->toBe(0)->and($grace->earlyLeaveMinutes)->toBe(0);
});

it('counts overtime only once it reaches the threshold, and takes the break off the worked time', function (): void {
    $calculator = new AttendanceCalculator;

    expect($calculator->calculate(acDay(), '09:00', '17:14')->overtimeMinutes)->toBe(0)->and($calculator->calculate(acDay(), '09:00', '17:15')->overtimeMinutes)->toBe(15)
        ->and($calculator->calculate(acDay(), '09:00', '19:00')->overtimeMinutes)->toBe(120)->and($calculator->calculate(acDay(), '09:00', '19:00')->workedMinutes)->toBe(570)
        ->and($calculator->calculate(new ShiftTimes('09:00', '17:00'), '09:00', '17:01')->overtimeMinutes)->toBe(1);
});

it('handles a night shift that ends the next morning', function (): void {
    $calculator = new AttendanceCalculator;
    $normal = $calculator->calculate(acNight(), '21:55', '06:05');
    $late = $calculator->calculate(acNight(), '22:25', '06:00');
    $extra = $calculator->calculate(acNight(), '22:00', '07:00');

    expect(acNight()->crossesMidnight())->toBeTrue()->and(acNight()->endMinutes())->toBe(1800)->and($normal->status)->toBe(AttendanceStatus::Present)->and($normal->workedMinutes)->toBe(480 + 10 - 30)
        ->and($late->lateMinutes)->toBe(25)->and($late->earlyLeaveMinutes)->toBe(0)->and($extra->overtimeMinutes)->toBe(60);
});

it('makes all the time worked on a holiday or a day off overtime, with nobody late', function (): void {
    $calculator = new AttendanceCalculator;
    $holiday = $calculator->calculate(acDay(), '10:00', '16:00', holiday: true);
    $dayOff = $calculator->calculate(null, '10:00', '16:00');

    expect($holiday->status)->toBe(AttendanceStatus::Holiday)->and($holiday->lateMinutes)->toBe(0)->and($holiday->overtimeMinutes)->toBe(330)->and($holiday->workedMinutes)->toBe(330)
        ->and($dayOff->status)->toBe(AttendanceStatus::DayOff)->and($dayOff->overtimeMinutes)->toBe(360);
});

it('calls a day with no punch absent only when a shift was rostered, and a single punch a missing punch', function (): void {
    $calculator = new AttendanceCalculator;

    expect($calculator->calculate(acDay(), null, null)->status)->toBe(AttendanceStatus::Absent)->and($calculator->calculate(null, null, null)->status)->toBe(AttendanceStatus::DayOff)
        ->and($calculator->calculate(acDay(), null, null, holiday: true)->status)->toBe(AttendanceStatus::Holiday)
        ->and($calculator->calculate(acDay(), '09:30', null)->status)->toBe(AttendanceStatus::Incomplete)->and($calculator->calculate(acDay(), '09:30', null)->lateMinutes)->toBe(30)
        ->and($calculator->calculate(acDay(), null, '17:00')->status)->toBe(AttendanceStatus::Incomplete);
});

it('groups punches into days with a first-in and a last-out, and a punch after midnight into the night shift before', function (): void {
    $grouper = new PunchGrouper;
    $night = fn (string $date): ?int => $date === '2026-03-02' || $date === '2026-03-03' ? 360 : null;

    $days = $grouper->group(['2026-03-01 09:02', '2026-03-01 12:30', '2026-03-01 17:05', '2026-03-02 21:58', '2026-03-03 06:04', '2026-03-03 21:50', '2026-03-04 05:59', '2026-03-05 08:00'], $night);

    expect($days['2026-03-01'])->toBe(['in' => '09:02', 'out' => '17:05'])->and($days['2026-03-02'])->toBe(['in' => '21:58', 'out' => '06:04'])->and($days['2026-03-03'])->toBe(['in' => '21:50', 'out' => '05:59'])
        ->and($days['2026-03-05'])->toBe(['in' => '08:00', 'out' => null])->and(array_keys($days))->toBe(['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-05']);

    // Without a night shift the morning punch is its own day.
    expect($grouper->group(['2026-03-02 21:58', '2026-03-03 06:04'], fn (string $date): ?int => null))->toBe(['2026-03-02' => ['in' => '21:58', 'out' => null], '2026-03-03' => ['in' => '06:04', 'out' => null]]);
});
