<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\LeavePolicy;
use Modules\HR\Models\LeaveType;

/**
 * Creates or changes a leave type and its policy together (Step 6.3). Codes are unique. Changing a policy affects later
 * grants and requests, not balances already granted.
 */
class SaveLeaveType extends Action
{
    /**
     * @param  array<string, mixed>  $data  code, name, is_paid, has_balance, counts_non_working_days, gender, color, document_after_days, is_active; policy: annual_days, accrual, carry_forward_max, min_service_months, employment_types, max_consecutive_days
     *
     * @throws HrRuleViolated
     */
    public function handle(?LeaveType $type, array $data): LeaveType
    {
        $code = strtoupper(trim((string) $data['code']));

        if (LeaveType::query()->withTrashed()->where('code', $code)->when($type instanceof LeaveType, fn ($query) => $query->whereKeyNot($type?->id))->exists()) {
            throw new HrRuleViolated(__('There is already a leave type with the code :code.', ['code' => $code]));
        }

        $balance = (bool) ($data['has_balance'] ?? true);
        $days = (string) ($data['annual_days'] ?? '0');

        if ($balance && (float) $days <= 0) {
            throw new HrRuleViolated(__('A leave type with a balance needs days per year, or untick "has a balance".'));
        }

        return $this->transaction(function () use ($type, $data, $code, $balance, $days): LeaveType {
            $type ??= new LeaveType;
            $type->fill([
                'code' => $code, 'name' => trim((string) $data['name']), 'is_paid' => (bool) ($data['is_paid'] ?? true), 'has_balance' => $balance, 'counts_non_working_days' => (bool) ($data['counts_non_working_days'] ?? false),
                'gender' => Gender::tryFrom((string) ($data['gender'] ?? '')), 'color' => (string) ($data['color'] ?? 'primary'),
                'document_after_days' => isset($data['document_after_days']) && $data['document_after_days'] !== '' ? (int) $data['document_after_days'] : null, 'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            $employment = array_values(array_filter((array) ($data['employment_types'] ?? []), fn (mixed $value): bool => EmploymentType::tryFrom((string) $value) !== null));
            LeavePolicy::query()->updateOrCreate(['leave_type_id' => $type->id], [
                'annual_days' => $days, 'accrual' => LeaveAccrual::tryFrom((string) ($data['accrual'] ?? '')) ?? LeaveAccrual::Yearly, 'carry_forward_max' => (string) ($data['carry_forward_max'] ?? '0'),
                'min_service_months' => (int) ($data['min_service_months'] ?? 0), 'employment_types' => $employment === [] ? null : $employment,
                'max_consecutive_days' => isset($data['max_consecutive_days']) && $data['max_consecutive_days'] !== '' ? (int) $data['max_consecutive_days'] : null,
            ]);

            return $type->load('policy');
        });
    }
}
