<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Models\LeaveType;

/**
 * The leave types a Bangladesh resort starts with (Step 6.3), following the Labour Act's usual figures: annual, casual,
 * sick, maternity and unpaid leave, each with its policy. Types the tenant already has (by code) are left alone.
 */
class SeedLeaveTypes extends Action
{
    /**
     * code => [name, paid, has balance, counts non-working days, gender, colour, document after days, policy].
     *
     * @var array<string, array{0: string, 1: bool, 2: bool, 3: bool, 4: Gender|null, 5: string, 6: int|null, 7: array<string, mixed>}>
     */
    public const array TYPES = [
        'ANN' => ['Annual leave', true, true, false, null, 'success', null, ['annual_days' => '14', 'accrual' => LeaveAccrual::Monthly, 'carry_forward_max' => '5', 'min_service_months' => 12, 'max_consecutive_days' => null]],
        'CAS' => ['Casual leave', true, true, false, null, 'info', null, ['annual_days' => '10', 'accrual' => LeaveAccrual::Yearly, 'carry_forward_max' => '0', 'min_service_months' => 0, 'max_consecutive_days' => 3]],
        'SICK' => ['Sick leave', true, true, false, null, 'warning', 3, ['annual_days' => '14', 'accrual' => LeaveAccrual::Yearly, 'carry_forward_max' => '0', 'min_service_months' => 0, 'max_consecutive_days' => null]],
        'MAT' => ['Maternity leave', true, true, true, Gender::Female, 'primary', null, ['annual_days' => '112', 'accrual' => LeaveAccrual::Yearly, 'carry_forward_max' => '0', 'min_service_months' => 6, 'max_consecutive_days' => 112]],
        'UNP' => ['Unpaid leave', false, false, false, null, 'secondary', null, ['annual_days' => '0', 'accrual' => LeaveAccrual::Yearly, 'carry_forward_max' => '0', 'min_service_months' => 0, 'max_consecutive_days' => null]],
    ];

    /**
     * @return int the leave types created
     */
    public function handle(): int
    {
        $created = 0;

        foreach (self::TYPES as $code => [$name, $paid, $balance, $nonWorking, $gender, $color, $document, $policy]) {
            if (LeaveType::query()->withTrashed()->where('code', $code)->exists()) {
                continue;
            }

            $type = LeaveType::query()->create(['code' => $code, 'name' => $name, 'is_paid' => $paid, 'has_balance' => $balance, 'counts_non_working_days' => $nonWorking, 'gender' => $gender, 'color' => $color, 'document_after_days' => $document, 'is_active' => true]);
            LeavePolicy::query()->create($policy + ['leave_type_id' => $type->id, 'employment_types' => $code === 'MAT' ? [EmploymentType::Permanent->value, EmploymentType::Contract->value] : null]);
            $created++;
        }

        return $created;
    }
}
