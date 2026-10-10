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
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\JournalEntryFactory;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;

/**
 * A journal entry (ARCHITECTURE §5.14, §7.2): a dated set of balanced debit and credit lines. A draft is
 * edited freely; a posted entry is immutable (the model refuses changes and deletion; only the reversal
 * link and the reversed status may be written when it is corrected by reversal). Automatic entries name
 * their source (source_type, source_id, source_event: unique, so a source event posts once).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $entry_no
 * @property Carbon $entry_date
 * @property int|null $fiscal_period_id
 * @property JournalStatus $status
 * @property string $description
 * @property string|null $reference
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $source_event
 * @property int|null $reverses_id
 * @property int|null $reversed_by_id
 * @property string $total
 * @property int|null $created_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 */
#[UseFactory(JournalEntryFactory::class)]
#[Fillable([
    'entry_no', 'entry_date', 'fiscal_period_id', 'status', 'description', 'reference', 'source_type', 'source_id', 'source_event', 'reverses_id',
    'reversed_by_id', 'total', 'created_by', 'posted_by', 'posted_at',
])]
class JournalEntry extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    use RecordsActivity;

    /** What may still be written on a posted entry: it being reversed. */
    private const array REVERSAL_FIELDS = ['status', 'reversed_by_id', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            $was = JournalStatus::tryFrom((string) $entry->getRawOriginal('status'));

            if ($was !== JournalStatus::Draft && array_diff(array_keys($entry->getDirty()), self::REVERSAL_FIELDS) !== []) {
                throw new AccountingRuleViolated(__('A posted entry cannot be changed: reverse it instead.'));
            }
        });

        static::deleting(function (self $entry): void {
            if ($entry->status !== JournalStatus::Draft) {
                throw new AccountingRuleViolated(__('A posted entry cannot be deleted: reverse it instead.'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['entry_date' => 'date', 'status' => JournalStatus::class, 'total' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }

    public function isDraft(): bool
    {
        return $this->status === JournalStatus::Draft;
    }
}
