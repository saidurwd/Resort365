<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Payroll\Database\Factories\PayrollRunFactory;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;

/**
 * One property's payroll for one month (Step 6.5). From approval the run is locked: the only change left is paying it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $run_no
 * @property Carbon $period
 * @property RunStatus $status
 * @property list<string>|null $include_annual
 * @property list<string>|null $warnings
 * @property int $employees_count
 * @property string $gross_total
 * @property string $deductions_total
 * @property string $tax_total
 * @property string $net_total
 * @property string $employer_total
 * @property int|null $approval_request_id
 * @property Carbon|null $calculated_at
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $paid_on
 * @property PayMethod|null $payment_method
 * @property string|null $payment_reference
 * @property int|null $paid_by
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read Collection<int, Payslip> $payslips
 */
#[UseFactory(PayrollRunFactory::class)]
#[Fillable(['property_id', 'run_no', 'period', 'status', 'include_annual', 'warnings', 'employees_count', 'gross_total', 'deductions_total', 'tax_total', 'net_total', 'employer_total', 'approval_request_id', 'calculated_at', 'approved_at', 'approved_by', 'paid_on', 'payment_method', 'payment_reference', 'paid_by', 'notes', 'created_by'])]
class PayrollRun extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    use RecordsActivity;

    /** What may still change on an approved run: it is paid. */
    private const array PAYING = ['status', 'paid_on', 'payment_method', 'payment_reference', 'paid_by', 'updated_at'];

    /** What changes when a pending run is approved. */
    private const array APPROVING = ['status', 'approved_at', 'approved_by', 'updated_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date', 'status' => RunStatus::class, 'include_annual' => 'array', 'warnings' => 'array', 'employees_count' => 'integer', 'gross_total' => 'decimal:2', 'deductions_total' => 'decimal:2',
            'tax_total' => 'decimal:2', 'net_total' => 'decimal:2', 'employer_total' => 'decimal:2', 'calculated_at' => 'datetime', 'approved_at' => 'datetime', 'paid_on' => 'date', 'payment_method' => PayMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $run): void {
            $original = $run->getOriginal('status');
            $was = $original instanceof RunStatus ? $original : RunStatus::tryFrom((string) $original);

            if ($was === null || ! in_array($was, [RunStatus::Pending, RunStatus::Approved, RunStatus::Paid], true)) {
                return;
            }

            $allowed = match ($was) {
                RunStatus::Pending => array_merge(self::APPROVING, ['approval_request_id']),
                RunStatus::Approved => self::PAYING,
                default => [],
            };

            if (array_diff(array_keys($run->getDirty()), $allowed) !== [] || ($was === RunStatus::Pending && ! in_array($run->status, [RunStatus::Pending, RunStatus::Approved, RunStatus::Calculated], true))) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status and cannot be changed.', ['no' => $run->run_no, 'status' => mb_strtolower($was->label())]));
            }
        });
        static::deleting(function (self $run): void {
            if (in_array($run->status, [RunStatus::Pending, RunStatus::Approved, RunStatus::Paid], true)) {
                throw new PayrollRuleViolated(__('Payroll run :no is :status and cannot be deleted.', ['no' => $run->run_no, 'status' => mb_strtolower($run->status->label())]));
            }
        });
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class)->orderBy('employee_name');
    }

    /** Whether the run still accepts changes to its payslips. */
    public function isOpen(): bool
    {
        return in_array($this->status, [RunStatus::Draft, RunStatus::Calculated], true);
    }
}
