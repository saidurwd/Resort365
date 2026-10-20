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
use Modules\Payroll\Database\Factories\LoanFactory;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\LoanStatus;
use Modules\Payroll\Enums\PayMethod;

/**
 * A loan or salary advance to an employee, paid out once and recovered by monthly installments through payroll (Step 6.5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $loan_no
 * @property int $employee_id
 * @property LoanKind $kind
 * @property string $principal
 * @property int $installments_count
 * @property Carbon $first_month
 * @property Carbon $disbursed_on
 * @property PayMethod $payment_method
 * @property string|null $reason
 * @property LoanStatus $status
 * @property int|null $created_by
 * @property-read Collection<int, LoanInstallment> $installments
 */
#[UseFactory(LoanFactory::class)]
#[Fillable(['property_id', 'loan_no', 'employee_id', 'kind', 'principal', 'installments_count', 'first_month', 'disbursed_on', 'payment_method', 'reason', 'status', 'created_by'])]
class Loan extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => LoanKind::class, 'principal' => 'decimal:2', 'installments_count' => 'integer', 'first_month' => 'date', 'disbursed_on' => 'date', 'payment_method' => PayMethod::class, 'status' => LoanStatus::class];
    }

    /**
     * @return HasMany<LoanInstallment, $this>
     */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('sequence');
    }
}
