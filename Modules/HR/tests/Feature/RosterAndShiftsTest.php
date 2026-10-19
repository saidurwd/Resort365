<?php

/*
| Shifts, rosters and holidays (Step 6.2): shift rules, assigning, clearing and copying roster weeks, and the holiday
| calendar with yearly repeats.
*/

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\HR\Actions\ClearRoster;
use Modules\HR\Actions\CopyRosterWeek;
use Modules\HR\Actions\DeleteShift;
use Modules\HR\Actions\SaveHoliday;
use Modules\HR\Actions\SaveShift;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Holiday;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;
use Modules\HR\Services\ScheduleLookup;

require_once __DIR__.'/../Support/hr-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rsShiftData(array $overrides = []): array
{
    return ['code' => 'ngt', 'name' => 'Night', 'starts_at' => '22:00', 'ends_at' => '06:00', 'break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15, ...$overrides];
}

it('saves a shift, upper-cases the code, and knows when it runs past midnight', function (): void {
    $shift = booking(fn (): Shift => app(SaveShift::class)->handle(null, rsShiftData()));

    expect($shift->code)->toBe('NGT')->and($shift->crossesMidnight())->toBeTrue()->and($shift->window())->toBe('22:00–06:00')->and($shift->times()->endMinutes())->toBe(1800)
        ->and(booking(fn (): Shift => app(SaveShift::class)->handle($shift, rsShiftData(['name' => 'Night watch', 'ends_at' => '07:00'])))->name)->toBe('Night watch');
});

it('refuses a duplicate code, equal start and end, and a break as long as the shift', function (): void {
    hrShift('DAY');
    $save = fn (array $data) => booking(fn () => app(SaveShift::class)->handle(null, rsShiftData($data)));

    expect(fn () => $save(['code' => 'day']))->toThrow(HrRuleViolated::class, 'already a shift')->and(fn () => $save(['starts_at' => '08:00', 'ends_at' => '08:00']))->toThrow(HrRuleViolated::class, 'same time')
        ->and(fn () => $save(['code' => 'X', 'starts_at' => '09:00', 'ends_at' => '10:00', 'break_minutes' => 60]))->toThrow(HrRuleViolated::class, 'shorter than the shift');
});

it('deletes a shift nobody used, and only deactivates one that has been rostered', function (): void {
    $used = hrShift('USED');
    $free = hrShift('FREE', '10:00', '18:00');
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-02', $used->id);

    booking(fn () => app(DeleteShift::class)->handle($free));

    expect(booking(fn (): bool => Shift::query()->whereKey($free->id)->exists()))->toBeFalse()->and(fn () => booking(fn () => app(DeleteShift::class)->handle($used)))->toThrow(HrRuleViolated::class, 'only be made inactive');
});

it('assigns a shift over a range, on chosen weekdays, replacing what was there, and skips dates outside employment', function (): void {
    $day = hrShift('DAY');
    $evening = hrShift('EVE', '14:00', '22:00');
    $early = hrEmployee(['first_name' => 'Early', 'joined_on' => '2026-03-04']);
    $employee = hrEmployee(['first_name' => 'Rahim']);

    expect(hrRoster([$employee->id, $early->id], '2026-03-02', '2026-03-08', $day->id))->toBe(7 + 5);

    // Monday 2 March 2026 … Sunday 8 March: replace Wednesdays and Fridays only.
    hrRoster([$employee->id], '2026-03-02', '2026-03-08', $evening->id, [3, 5]);
    $codes = booking(fn (): array => Roster::query()->where('employee_id', $employee->id)->orderBy('work_date')->with('shift')->get()->mapWithKeys(fn (Roster $roster): array => [$roster->work_date->toDateString() => $roster->shift?->code])->all());

    expect($codes['2026-03-04'])->toBe('EVE')->and($codes['2026-03-06'])->toBe('EVE')->and($codes['2026-03-05'])->toBe('DAY')->and($codes)->toHaveCount(7)
        ->and(booking(fn (): int => Roster::query()->where('employee_id', $early->id)->count()))->toBe(5);

    hrRoster([$employee->id], '2026-03-07', '2026-03-07', null);
    expect(booking(fn (): bool => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-03-07')->sole()->shift_id === null))->toBeTrue();
});

it('refuses a bad range, an inactive shift, no employees and more than 93 days', function (): void {
    $shift = hrShift('DAY');
    $off = hrShift('OLD', '09:00', '17:00', ['is_active' => false]);
    $employee = hrEmployee();

    expect(fn () => hrRoster([$employee->id], '2026-03-05', '2026-03-01', $shift->id))->toThrow(HrRuleViolated::class, 'before the first')
        ->and(fn () => hrRoster([$employee->id], '2026-03-01', '2026-03-05', $off->id))->toThrow(HrRuleViolated::class, 'active shift')
        ->and(fn () => hrRoster([], '2026-03-01', '2026-03-05', $shift->id))->toThrow(HrRuleViolated::class, 'at least one employee')
        ->and(fn () => hrRoster([$employee->id], '2026-01-01', '2026-06-01', $shift->id))->toThrow(HrRuleViolated::class, '93 days');
});

it('clears roster entries in a range and copies a week forward, days off included', function (): void {
    $day = hrShift('DAY');
    $employee = hrEmployee();
    hrRoster([$employee->id], '2026-03-02', '2026-03-07', $day->id);
    hrRoster([$employee->id], '2026-03-08', '2026-03-08', null);

    expect(booking(fn (): int => app(CopyRosterWeek::class)->handle([$employee->id], '2026-03-02', 2)))->toBe(14)->and(booking(fn (): int => Roster::query()->where('employee_id', $employee->id)->count()))->toBe(21)
        ->and(booking(fn (): bool => Roster::query()->where('employee_id', $employee->id)->whereDate('work_date', '2026-03-22')->sole()->shift_id === null))->toBeTrue()
        ->and(booking(fn (): int => app(ClearRoster::class)->handle([$employee->id], '2026-03-09', '2026-03-22')))->toBe(14)->and(booking(fn (): int => Roster::query()->count()))->toBe(7)
        ->and(fn () => booking(fn () => app(CopyRosterWeek::class)->handle([$employee->id], '2026-06-01', 2)))->toThrow(HrRuleViolated::class, 'no roster')
        ->and(fn () => booking(fn () => app(CopyRosterWeek::class)->handle([$employee->id], '2026-03-02', 13)))->toThrow(HrRuleViolated::class, 'between 1 and 12');
});

it('keeps holidays one per date, and repeats a yearly holiday on the same day every year', function (): void {
    $save = fn (?Holiday $holiday, array $data) => booking(fn (): Holiday => app(SaveHoliday::class)->handle($holiday, [...$data, 'property_id' => bookingIds()['property']]));
    $victory = $save(null, ['name' => 'Victory Day', 'holiday_date' => '2025-12-16', 'repeats_yearly' => true]);
    $save(null, ['name' => 'Staff day', 'holiday_date' => '2026-09-12', 'is_paid' => false]);
    $leap = $save(null, ['name' => 'Leap day', 'holiday_date' => '2024-02-29', 'repeats_yearly' => true]);
    $found = fn (string $from, string $to): array => booking(fn (): array => array_map(fn (Holiday $holiday): string => $holiday->name, app(ScheduleLookup::class)->holidays(bookingIds()['property'], $from, $to)));

    expect($found('2026-01-01', '2026-12-31'))->toBe(['2026-02-28' => 'Leap day', '2026-09-12' => 'Staff day', '2026-12-16' => 'Victory Day'])->and($found('2027-12-01', '2027-12-31'))->toBe(['2027-12-16' => 'Victory Day'])
        ->and($found('2028-02-01', '2028-02-29'))->toBe(['2028-02-29' => 'Leap day'])->and($found('2026-09-13', '2026-12-15'))->toBe([])
        ->and(fn () => $save(null, ['name' => 'Another', 'holiday_date' => '2026-09-12']))->toThrow(HrRuleViolated::class, 'already a holiday')
        ->and($save($victory, ['name' => 'Victory Day (Bijoy Dibos)', 'holiday_date' => '2025-12-16', 'repeats_yearly' => true])->name)->toBe('Victory Day (Bijoy Dibos)')
        ->and($leap->is_paid)->toBeTrue();
});
