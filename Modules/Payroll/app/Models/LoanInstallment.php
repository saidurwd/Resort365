<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Payroll\Database\Factories\LoanInstallmentFactory;
use Modules\Payroll\Enums\InstallmentStatus;

/**
 * One month's recovery of a loan (Step 6.5): scheduled, then deducted by an approved payroll run, or written off.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $loan_id
 * @property int $sequence
 * @property Carbon $due_month
 * @property string $amount
 * @property InstallmentStatus $status
 * @property int|null $payslip_id
 * @property Carbon|null $settled_at
 * @property string|null $note
 * @property-read Loan $loan
 */
#[UseFactory(LoanInstallmentFactory::class)]
#[Fillable(['loan_id', 'sequence', 'due_month', 'amount', 'status', 'payslip_id', 'settled_at', 'note'])]
class LoanInstallment extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LoanInstallmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'due_month' => 'date', 'amount' => 'decimal:2', 'status' => InstallmentStatus::class, 'settled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
