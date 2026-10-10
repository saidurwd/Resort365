<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\FundTransferFactory;
use Modules\Accounting\Enums\VoucherStatus;

/**
 * Money moved between two of the tenant's cash or bank accounts (Step 4.4), posted when saved.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $transfer_no
 * @property Carbon $transfer_date
 * @property int $from_bank_account_id
 * @property int $to_bank_account_id
 * @property string $amount
 * @property string|null $reference
 * @property string|null $notes
 * @property VoucherStatus $status
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property-read BankAccount $from
 * @property-read BankAccount $to
 */
#[UseFactory(FundTransferFactory::class)]
#[Fillable(['property_id', 'transfer_no', 'transfer_date', 'from_bank_account_id', 'to_bank_account_id', 'amount', 'reference', 'notes', 'status', 'journal_entry_id', 'created_by', 'voided_by', 'voided_at', 'void_reason'])]
class FundTransfer extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<FundTransferFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'amount' => 'decimal:2', 'status' => VoucherStatus::class, 'voided_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
