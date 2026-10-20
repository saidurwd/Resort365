<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Database\Factories\PayslipFactory;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * One employee's pay for the run's month (Step 6.5), with a snapshot of who they were. Refuses any change once the run is
 * waiting for approval, approved or paid.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property string $employee_no
 * @property string $employee_name
 * @property string|null $employee_email
 * @property string $designation
 * @property int|null $department_id
 * @property string|null $department_name
 * @property string $structure_name
 * @property string $basic
 * @property int $days_in_month
 * @property int $days_employed
 * @property string $pay_fraction
 * @property string $absent_days
 * @property string $unpaid_leave_days
 * @property int $overtime_minutes
 * @property string $gross
 * @property string $deductions
 * @property string $tax
 * @property string $net
 * @property string $employer_cost
 * @property list<int>|null $installment_ids
 * @property Carbon|null $emailed_at
 * @property-read PayrollRun $run
 * @property-read Collection<int, PayslipLine> $lines
 */
#[UseFactory(PayslipFactory::class)]
#[Fillable([
    'property_id', 'payroll_run_id', 'employee_id', 'employee_no', 'employee_name', 'employee_email', 'designation', 'department_id', 'department_name', 'structure_name', 'basic', 'days_in_month', 'days_employed',
    'pay_fraction', 'absent_days', 'unpaid_leave_days', 'overtime_minutes', 'gross', 'deductions', 'tax', 'net', 'employer_cost', 'installment_ids', 'emailed_at',
])]
class Payslip extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic' => 'decimal:2', 'pay_fraction' => 'decimal:4', 'absent_days' => 'decimal:1', 'unpaid_leave_days' => 'decimal:1', 'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'tax' => 'decimal:2', 'net' => 'decimal:2',
            'employer_cost' => 'decimal:2', 'installment_ids' => 'array', 'emailed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $payslip): void {
            // Sending the payslip by email only stamps it; nothing about the pay changes.
            if (array_diff(array_keys($payslip->getDirty()), ['emailed_at', 'updated_at']) !== []) {
                self::assertRunOpen($payslip->payroll_run_id);
            }
        });
        static::deleting(fn (self $payslip) => self::assertRunOpen($payslip->payroll_run_id));
    }

    /**
     * @throws PayrollRuleViolated
     */
    public static function assertRunOpen(int $runId): void
    {
        $status = RunStatus::tryFrom((string) DB::table('payroll_runs')->where('id', $runId)->value('status'));

        if ($status !== null && ! in_array($status, [RunStatus::Draft, RunStatus::Calculated], true)) {
            throw new PayrollRuleViolated(__('This payroll run is :status, so its payslips cannot be changed.', ['status' => mb_strtolower($status->label())]));
        }
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return HasMany<PayslipLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayslipLine::class)->orderBy('sort_order')->orderBy('id');
    }
}
