<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\BankStatementFactory;

/**
 * An imported bank statement (Step 4.4): its lines are matched to ledger lines on the reconciliation screen.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bank_account_id
 * @property string $file_name
 * @property Carbon $statement_from
 * @property Carbon $statement_to
 * @property string $closing_balance
 * @property int $line_count
 * @property int|null $imported_by
 * @property-read BankAccount $bankAccount
 * @property-read BankReconciliation|null $reconciliation
 */
#[UseFactory(BankStatementFactory::class)]
#[Fillable(['bank_account_id', 'file_name', 'statement_from', 'statement_to', 'closing_balance', 'line_count', 'imported_by'])]
class BankStatement extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BankStatementFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['statement_from' => 'date', 'statement_to' => 'date', 'closing_balance' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return HasMany<BankStatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('line_no');
    }

    /**
     * @return HasOne<BankReconciliation, $this>
     */
    public function reconciliation(): HasOne
    {
        return $this->hasOne(BankReconciliation::class);
    }
}
