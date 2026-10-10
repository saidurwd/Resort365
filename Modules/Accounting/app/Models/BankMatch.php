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
use Modules\Accounting\Database\Factories\BankMatchFactory;

/**
 * A statement line matched to a ledger line (one to one). Once its reconciliation is completed it is locked.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bank_statement_line_id
 * @property int $journal_line_id
 * @property int|null $bank_reconciliation_id
 * @property int|null $matched_by
 * @property Carbon $matched_at
 * @property-read BankStatementLine $statementLine
 * @property-read JournalLine $journalLine
 */
#[UseFactory(BankMatchFactory::class)]
#[Fillable(['bank_statement_line_id', 'journal_line_id', 'bank_reconciliation_id', 'matched_by', 'matched_at'])]
class BankMatch extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BankMatchFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['matched_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<BankStatementLine, $this>
     */
    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    /**
     * @return BelongsTo<JournalLine, $this>
     */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }

    public function isLocked(): bool
    {
        return $this->bank_reconciliation_id !== null;
    }
}
