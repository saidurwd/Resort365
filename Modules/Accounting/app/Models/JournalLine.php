<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\JournalLineFactory;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Enums\PartyType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;

/**
 * One debit or credit of a journal entry, with its dimensions: property, department (cost centre) and
 * party (ARCHITECTURE §5.14). Lines of a posted entry are immutable (the model refuses to add, change or
 * delete them); they are written with their entry, so not activity-logged on their own.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $journal_entry_id
 * @property int $line_no
 * @property int $account_id
 * @property string $debit
 * @property string $credit
 * @property string|null $description
 * @property int|null $property_id
 * @property int|null $department_id
 * @property PartyType|null $party_type
 * @property int|null $party_id
 */
#[UseFactory(JournalLineFactory::class)]
#[Fillable(['journal_entry_id', 'line_no', 'account_id', 'debit', 'credit', 'description', 'property_id', 'department_id', 'party_type', 'party_id'])]
class JournalLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        $guard = function (self $line): void {
            $status = JournalEntry::query()->whereKey($line->journal_entry_id)->value('status');

            if ($status instanceof JournalStatus) {
                $status = $status->value;
            }

            if ($status !== null && $status !== JournalStatus::Draft->value) {
                throw new AccountingRuleViolated(__('The lines of a posted entry cannot be changed: reverse the entry instead.'));
            }
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'party_type' => PartyType::class];
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
