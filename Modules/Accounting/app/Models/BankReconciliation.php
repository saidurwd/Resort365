<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\BankReconciliationFactory;

/**
 * A completed reconciliation of a statement (Step 4.4): the figures as they stood, difference zero.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bank_statement_id
 * @property int $bank_account_id
 * @property Carbon $statement_date
 * @property string $statement_balance
 * @property string $book_balance
 * @property string $outstanding_net
 * @property string $difference
 * @property int|null $completed_by
 * @property Carbon $completed_at
 */
#[UseFactory(BankReconciliationFactory::class)]
#[Fillable(['bank_statement_id', 'bank_account_id', 'statement_date', 'statement_balance', 'book_balance', 'outstanding_net', 'difference', 'completed_by', 'completed_at'])]
class BankReconciliation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BankReconciliationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['statement_date' => 'date', 'statement_balance' => 'decimal:2', 'book_balance' => 'decimal:2', 'outstanding_net' => 'decimal:2', 'difference' => 'decimal:2', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<BankStatement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }
}
