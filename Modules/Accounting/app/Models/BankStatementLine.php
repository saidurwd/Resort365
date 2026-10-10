<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\BankStatementLineFactory;

/**
 * One line of a bank statement. A deposit matches a debit of the bank's ledger account, a withdrawal a credit.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bank_statement_id
 * @property int $bank_account_id
 * @property int $line_no
 * @property Carbon $txn_date
 * @property string $description
 * @property string|null $reference
 * @property string $withdrawal
 * @property string $deposit
 * @property string|null $balance
 * @property string $fingerprint
 * @property-read BankMatch|null $match
 */
#[UseFactory(BankStatementLineFactory::class)]
#[Fillable(['bank_statement_id', 'bank_account_id', 'line_no', 'txn_date', 'description', 'reference', 'withdrawal', 'deposit', 'balance', 'fingerprint'])]
class BankStatementLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BankStatementLineFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['txn_date' => 'date', 'withdrawal' => 'decimal:2', 'deposit' => 'decimal:2', 'balance' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    /**
     * @return HasOne<BankMatch, $this>
     */
    public function match(): HasOne
    {
        return $this->hasOne(BankMatch::class);
    }

    /** Deposit positive, withdrawal negative: the same sign as the ledger line's debit minus credit. */
    public function signedAmount(): string
    {
        return bcsub((string) $this->deposit, (string) $this->withdrawal, 2);
    }
}
