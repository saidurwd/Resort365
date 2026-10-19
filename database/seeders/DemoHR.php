<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\HR\Actions\AssignRoster;
use Modules\HR\Actions\ConfirmEmployee;
use Modules\HR\Actions\ExitEmployee;
use Modules\HR\Actions\ImportAttendance;
use Modules\HR\Actions\LinkEmployeeUser;
use Modules\HR\Actions\PromoteEmployee;
use Modules\HR\Actions\RecordAttendance;
use Modules\HR\Actions\RecordDisciplinary;
use Modules\HR\Actions\RecordIncrement;
use Modules\HR\Actions\SaveDesignation;
use Modules\HR\Actions\SaveEmployee;
use Modules\HR\Actions\SaveGrade;
use Modules\HR\Actions\SaveHoliday;
use Modules\HR\Actions\SaveShift;
use Modules\HR\Actions\TransferEmployee;
use Modules\HR\Enums\AttendanceStatus;
use Modules\HR\Enums\DisciplinaryAction;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Models\Designation;
use Modules\HR\Models\Employee;
use Modules\HR\Models\Grade;
use Modules\HR\Models\Shift;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * HR demo (Steps 6.1 and 6.2): designations and grades, then about 60 employees at Rodela across the 11 departments in a
 * reporting tree (the General Manager on top, department heads, staff), the demo users' own employee records linked to
 * their accounts, and a little history: a new hire on probation, a confirmation, a promotion, an increment, a written
 * warning, a move to another department and a resignation. Green Valley gets a small team.
 */
final class DemoHR
{
    /** @var array<string, array{0: string, 1: int}> grade code => [name, level] */
    private const array GRADES = ['G1' => ['Trainee', 1], 'G2' => ['Staff', 2], 'G3' => ['Senior staff', 3], 'G4' => ['Supervisor', 4], 'G5' => ['Manager', 5], 'G6' => ['Executive', 6]];

    /**
     * Rodela's team: department code => list of [designation, grade, how many]. A head comes first in each department; a
     * demo user's mailbox (see USERS) takes the first matching designation.
     *
     * @var array<string, list<array{0: string, 1: string, 2: int}>>
     */
    private const array TEAM = [
        'ADM' => [['General Manager', 'G6', 1], ['Executive Assistant', 'G3', 1]],
        'FO' => [['Front Office Manager', 'G5', 1], ['Receptionist', 'G2', 4], ['Reservations Agent', 'G2', 1], ['Bellman', 'G2', 2]],
        'HK' => [['Housekeeping Supervisor', 'G4', 1], ['Room Attendant', 'G2', 10], ['Laundry Attendant', 'G2', 2]],
        'FBS' => [['F&B Manager', 'G5', 1], ['Waiter', 'G2', 5], ['Cashier', 'G2', 2], ['Bartender', 'G2', 2]],
        'FBP' => [['Executive Chef', 'G5', 1], ['Sous Chef', 'G4', 1], ['Commis Chef', 'G1', 6]],
        'ENG' => [['Maintenance Technician', 'G3', 1], ['Electrician', 'G3', 1], ['Plumber', 'G3', 1]],
        'SM' => [['Sales Manager', 'G5', 1], ['Sales Executive', 'G3', 2]],
        'ACC' => [['Accountant', 'G4', 1], ['Accounts Assistant', 'G2', 2]],
        'HR' => [['HR Manager', 'G5', 1], ['Payroll Officer', 'G3', 1]],
        'SEC' => [['Security Supervisor', 'G4', 1], ['Security Guard', 'G2', 3]],
        'STR' => [['Storekeeper', 'G3', 1], ['Procurement Officer', 'G3', 1], ['Store Assistant', 'G2', 1]],
    ];

    /** @var array<string, string> demo mailbox => designation the user's own employee record gets */
    private const array USERS = [
        'gm' => 'General Manager', 'fomanager' => 'Front Office Manager', 'frontdesk' => 'Receptionist', 'reservations' => 'Reservations Agent', 'housekeeping' => 'Housekeeping Supervisor',
        'maintenance' => 'Maintenance Technician', 'fnb' => 'F&B Manager', 'cashier' => 'Cashier', 'waiter' => 'Waiter', 'chef' => 'Executive Chef', 'bartender' => 'Bartender',
        'store' => 'Storekeeper', 'procurement' => 'Procurement Officer', 'accountant' => 'Accountant', 'hr' => 'HR Manager', 'payroll' => 'Payroll Officer',
    ];

    /** Green Valley's team: department code => designations. */
    private const array SMALL_TEAM = [
        'ADM' => [['General Manager', 'G6']], 'FO' => [['Front Office Manager', 'G5'], ['Receptionist', 'G2']], 'HK' => [['Housekeeping Supervisor', 'G4'], ['Room Attendant', 'G2'], ['Room Attendant', 'G2']],
        'FBP' => [['Executive Chef', 'G5']], 'ENG' => [['Maintenance Technician', 'G3']],
    ];

    private const array FIRST_NAMES = [
        ['Rahim', 'Karim', 'Jamal', 'Sohel', 'Imran', 'Tanvir', 'Mizan', 'Shafiq', 'Arif', 'Nasir', 'Habib', 'Faisal', 'Rubel', 'Masud', 'Sajib', 'Rasel', 'Monir', 'Babul', 'Alamgir', 'Kamrul'],
        ['Nusrat', 'Farzana', 'Sabina', 'Rumana', 'Shirin', 'Mitu', 'Lipi', 'Rokeya', 'Tania', 'Salma', 'Jesmin', 'Parveen', 'Shahana', 'Moushumi', 'Ritu', 'Nasima', 'Popy', 'Sumi', 'Amena', 'Dolly'],
    ];

    private const array LAST_NAMES = ['Ahmed', 'Hossain', 'Rahman', 'Islam', 'Chowdhury', 'Akter', 'Begum', 'Khan', 'Sarker', 'Mia', 'Uddin', 'Sheikh', 'Talukder', 'Das', 'Barua', 'Majumder'];

    public static function seed(Tenant $tenant, int $propertyId, string $domain, bool $full = true): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain, $full): void {
            if (Employee::query()->exists()) {
                return;
            }

            $departments = collect(app(DepartmentDirectory::class)->all(false))->keyBy('code');
            $users = collect(app(UserDirectory::class)->all())->keyBy('email');

            foreach (self::GRADES as $code => [$name, $level]) {
                app(SaveGrade::class)->handle(null, ['code' => $code, 'name' => $name, 'level' => $level, 'is_active' => true]);
            }

            $grades = Grade::query()->pluck('id', 'code');
            $team = $full ? self::TEAM : self::smallTeam();
            $made = [];

            foreach ($team as $code => $designations) {
                foreach ($designations as [$designation]) {
                    $made[$designation] ??= app(SaveDesignation::class)->handle(null, ['name' => $designation, 'department_id' => $departments[$code]->id ?? null, 'is_active' => true]);
                }
            }

            if ($full) {
                $made['Senior Receptionist'] = app(SaveDesignation::class)->handle(null, ['name' => 'Senior Receptionist', 'department_id' => $departments['FO']->id ?? null, 'is_active' => true]);
            }

            $counter = 0;
            $general = null;
            $heads = [];
            $byDesignation = [];
            $mailboxes = array_flip(self::USERS);

            foreach ($team as $code => $designations) {
                foreach ($designations as $row) {
                    [$designation, $grade] = $row;
                    $count = $row[2];

                    for ($n = 0; $n < $count; $n++) {
                        $mailbox = $full && ! isset($byDesignation[$designation]) ? ($mailboxes[$designation] ?? null) : null;
                        $user = $mailbox !== null ? $users->get($mailbox.'@'.$domain) : null;
                        [$first, $last, $gender] = $user !== null && in_array($mailbox, ['frontdesk', 'accountant'], true) ? [...self::splitName($user->name), 'female'] : self::person($counter);
                        $counter++;
                        $head = $heads[$code] ?? null;
                        $joined = now()->subMonths(14 + ($counter % 30))->subDays($counter % 20)->toDateString();
                        $employee = app(SaveEmployee::class)->handle(null, [
                            'property_id' => $propertyId, 'first_name' => $first, 'last_name' => $last, 'gender' => $gender, 'marital_status' => $counter % 3 === 0 ? 'single' : 'married',
                            'date_of_birth' => now()->subYears(22 + $counter % 25)->subDays($counter * 7)->toDateString(), 'nationality_code' => 'BD',
                            'email' => $user->email ?? strtolower($first.'.'.$last.$counter).'@'.$domain, 'phone' => '017'.str_pad((string) (11000000 + $counter * 137), 8, '0', STR_PAD_LEFT),
                            'address' => ['line1' => 'House '.(10 + $counter).', Road '.(1 + $counter % 9), 'city' => $full ? "Cox's Bazar" : 'Sreemangal'],
                            'emergency_contact' => ['name' => $last.' family', 'relation' => 'Relative', 'phone' => '018'.str_pad((string) (22000000 + $counter * 211), 8, '0', STR_PAD_LEFT)],
                            'national_id' => '1990'.str_pad((string) (100000000 + $counter * 7919), 9, '0', STR_PAD_LEFT), 'bank_name' => 'City Bank', 'bank_branch' => 'Main branch',
                            'bank_account_name' => $first.' '.$last, 'bank_account_no' => '1101'.str_pad((string) (200000 + $counter * 331), 8, '0', STR_PAD_LEFT),
                            'department_id' => $departments[$code]->id, 'designation_id' => $made[$designation]->id, 'grade_id' => $grades[$grade], 'manager_id' => $head ?? ($code === 'ADM' ? null : $general),
                            'employment_type' => $designation === 'Commis Chef' && $n % 3 === 0 ? 'contract' : 'permanent', 'joined_on' => $joined,
                        ]);

                        $general ??= $code === 'ADM' ? $employee->id : null;
                        $heads[$code] ??= $employee->id;
                        $byDesignation[$designation][] = $employee;

                        if ($user !== null) {
                            app(LinkEmployeeUser::class)->handle($employee, $user->id);
                        }
                    }
                }
            }

            if ($full) {
                self::history($byDesignation, $departments->all(), $made, $grades->all(), $propertyId, $heads);
            }
        });
    }

    /**
     * Shifts, holidays, two months of rosters and a month of imported attendance (Step 6.2): the previous month's punches
     * go through the real CSV import, with late arrivals, early leavers, overtime, missing punches, night shifts and the
     * staff day, and a few days corrected by hand.
     */
    public static function attendance(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (Shift::query()->exists() || ! Employee::query()->exists()) {
                return;
            }

            $shifts = [];

            foreach ([['GEN', 'General', '09:00', '17:00', 'primary'], ['MOR', 'Morning', '06:00', '14:00', 'success'], ['EVE', 'Evening', '14:00', '22:00', 'warning'], ['NGT', 'Night', '22:00', '06:00', 'secondary']] as [$code, $name, $start, $end, $color]) {
                $shifts[$code] = app(SaveShift::class)->handle(null, ['code' => $code, 'name' => $name, 'starts_at' => $start, 'ends_at' => $end, 'break_minutes' => 30, 'grace_minutes' => 10, 'overtime_after_minutes' => 15, 'color' => $color, 'is_active' => true]);
            }

            $lastMonth = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

            foreach ([['Language Martyrs\' Day', '02-21'], ['Independence Day', '03-26'], ['Bengali New Year', '04-14'], ['May Day', '05-01'], ['Victory Day', '12-16'], ['Christmas Day', '12-25']] as [$name, $monthDay]) {
                app(SaveHoliday::class)->handle(null, ['name' => $name, 'holiday_date' => $lastMonth->year.'-'.$monthDay, 'is_paid' => true, 'repeats_yearly' => true, 'property_id' => $propertyId]);
            }

            $staffDay = $lastMonth->addDays(11)->toDateString();
            app(SaveHoliday::class)->handle(null, ['name' => 'Annual staff day', 'holiday_date' => $staffDay, 'is_paid' => true, 'repeats_yearly' => false, 'property_id' => $propertyId]);

            // Rosters from the first of last month to the end of this month: a shift by department, one weekly day off each.
            $employees = Employee::query()->where('status', '!=', EmployeeStatus::Exited->value)->orderBy('id')->get();
            $departments = collect(app(DepartmentDirectory::class)->all(false))->pluck('code', 'id');
            $from = $lastMonth->toDateString();
            $to = CarbonImmutable::now()->endOfMonth()->toDateString();
            $groups = [];

            foreach ($employees->values() as $index => $employee) {
                $code = $departments[$employee->department_id] ?? 'ADM';
                $shift = match ($code) {
                    'HK' => 'MOR', 'FBS', 'FBP' => $index % 2 === 0 ? 'MOR' : 'EVE', 'FO' => ['MOR', 'EVE', 'GEN'][$index % 3], 'SEC' => $index % 2 === 0 ? 'NGT' : 'MOR', default => 'GEN',
                };
                $groups[$shift.'|'.($index % 7)][] = $employee->id;
            }

            foreach ($groups as $key => $ids) {
                [$shift, $off] = explode('|', $key);
                app(AssignRoster::class)->handle($ids, $from, $to, $shifts[$shift]->id, array_values(array_diff(range(0, 6), [(int) $off])));
                app(AssignRoster::class)->handle($ids, $from, $to, null, [(int) $off]);
            }

            // Last month's punches, as a biometric export: first and last punch of each rostered day.
            $lines = ['Employee No,Date Time'];
            $end = $lastMonth->endOfMonth();

            foreach ($employees as $employee) {
                $shift = $shifts[collect($groups)->filter(fn (array $ids): bool => in_array($employee->id, $ids, true))->keys()->map(fn (string $key): string => explode('|', $key)[0])->first() ?? 'GEN'];
                $off = (int) explode('|', (string) collect($groups)->filter(fn (array $ids): bool => in_array($employee->id, $ids, true))->keys()->first())[1];

                for ($day = $lastMonth; $day->lte($end); $day = $day->addDay()) {
                    $date = $day->toDateString();
                    $isStaffDay = $date === $staffDay;

                    if ($day->dayOfWeek === $off || $date < $employee->joined_on->toDateString() || ($employee->exited_on !== null && $date > $employee->exited_on->toDateString())) {
                        continue;
                    }

                    $roll = abs(crc32($employee->employee_no.$date)) % 100;
                    $start = $shift->times()->startMinutes();
                    $finish = $shift->times()->endMinutes();
                    $in = $start - ($roll % 9);
                    $out = $finish + ($roll % 11);

                    if ($isStaffDay) {
                        if ($roll % 3 !== 0) {
                            continue;
                        }
                    } elseif ($roll < 6) {
                        continue;
                    } elseif ($roll < 22) {
                        $in = $start + 12 + ($roll * 7) % 34;
                    } elseif ($roll < 28) {
                        $out = $finish - 15 - ($roll * 3) % 46;
                    } elseif ($roll >= 86) {
                        $out = $finish + 20 + ($roll * 5) % 100;
                    }

                    $lines[] = $employee->employee_no.','.$day->startOfDay()->addMinutes($in)->format('Y-m-d H:i');
                    $lines[] = $employee->employee_no.','.$day->startOfDay()->addMinutes($roll === 6 ? $in : $out)->format('Y-m-d H:i');
                }
            }

            app(ImportAttendance::class)->handle($propertyId, 'biometric-'.$lastMonth->format('Y-m').'.csv', implode("\n", $lines), null);

            // A few days put right by hand.
            $manual = Employee::query()->where('status', '!=', EmployeeStatus::Exited->value)->orderBy('id')->skip(3)->take(3)->get();

            if ($manual->count() === 3) {
                $day = $lastMonth->addDays(8)->toDateString();
                app(RecordAttendance::class)->handle($manual[0], $day, '09:05', '17:10', null, 'Device was down; confirmed by the supervisor', null);
                app(RecordAttendance::class)->handle($manual[1], $day, '09:00', '13:00', AttendanceStatus::HalfDay, 'Went home sick at lunch', null);
                app(RecordAttendance::class)->handle($manual[2], $day, null, null, AttendanceStatus::Absent, 'No call, no show', null);
            }
        });
    }

    /**
     * @return array<string, list<array{0: string, 1: string, 2: int}>>
     */
    private static function smallTeam(): array
    {
        return array_map(fn (array $rows): array => array_map(fn (array $row): array => [$row[0], $row[1], 1], $rows), self::SMALL_TEAM);
    }

    /**
     * @param  array<string, list<Employee>>  $by
     * @param  array<string, object>  $departments
     * @param  array<string, Designation>  $designations
     * @param  array<string, int>  $grades
     * @param  array<string, int>  $heads
     */
    private static function history(array $by, array $departments, array $designations, array $grades, int $propertyId, array $heads): void
    {
        $day = fn (int $daysAgo): string => now()->subDays($daysAgo)->toDateString();
        $hire = fn (string $designation, string $deptCode, string $grade, string $joined, ?string $probationEnds, string $first, string $last): Employee => app(SaveEmployee::class)->handle(null, [
            'property_id' => $propertyId, 'first_name' => $first, 'last_name' => $last, 'gender' => 'female', 'phone' => '019'.random_int(10000000, 99999999), 'national_id' => '1998'.random_int(100000000, 999999999), 'department_id' => $departments[$deptCode]->id,
            'designation_id' => $designations[$designation]->id, 'grade_id' => $grades[$grade], 'manager_id' => $heads[$deptCode], 'employment_type' => 'permanent', 'joined_on' => $joined, 'probation_ends_on' => $probationEnds,
        ]);

        // A new hire on probation, one confirmed after probation, and one who resigned.
        $hire('Room Attendant', 'HK', 'G1', $day(45), now()->addDays(45)->toDateString(), 'Mim', 'Akter');
        $confirmed = $hire('Receptionist', 'FO', 'G2', $day(240), $day(150), 'Tania', 'Sultana');
        app(ConfirmEmployee::class)->handle($confirmed, $day(150), 'Good guest feedback', null);
        $resigned = $hire('Room Attendant', 'HK', 'G2', $day(700), null, 'Rina', 'Khatun');
        app(ExitEmployee::class)->handle($resigned, ExitReason::Resignation, $day(30), 'Returned to her village', null);

        // A promotion, an increment, a written warning and a move to another department.
        $receptionist = $by['Receptionist'][1] ?? null;

        if ($receptionist instanceof Employee) {
            app(PromoteEmployee::class)->handle($receptionist, $designations['Senior Receptionist']->id, $grades['G3'], $day(120), 'Promoted after a year on the desk', null);
        }

        $waiter = $by['Waiter'][1] ?? null;

        if ($waiter instanceof Employee) {
            app(RecordIncrement::class)->handle($waiter, '18000', '19500', $day(90), 'Annual increment', null);
        }

        $guard = $by['Security Guard'][0] ?? null;

        if ($guard instanceof Employee) {
            app(RecordDisciplinary::class)->handle($guard, DisciplinaryAction::WrittenWarning, $day(60), 'Left the gate unattended during the night shift', null);
        }

        $assistant = $by['Store Assistant'][0] ?? null;

        if ($assistant instanceof Employee) {
            app(TransferEmployee::class)->handle($assistant, $propertyId, $departments['FBP']->id, $day(20), 'Moved to the kitchen store', null);
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function person(int $counter): array
    {
        $gender = $counter % 3 === 0 ? 1 : 0;
        $names = self::FIRST_NAMES[$gender];

        return [$names[$counter % count($names)], self::LAST_NAMES[($counter * 5) % count(self::LAST_NAMES)], $gender === 1 ? 'female' : 'male'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
