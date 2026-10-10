<?php

namespace Modules\Accounting\Models;

use App\Support\Attachments\HasAttachments;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\VoucherFactory;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Enums\VoucherStatus;
use Modules\Accounting\Enums\VoucherType;
use Spatie\MediaLibrary\HasMedia;

/**
 * A quick income or expense voucher (ARCHITECTURE §5.14): receipts and invoices are attachments. Posted when
 * saved; a void reverses its journal entry.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $voucher_no
 * @property VoucherType $type
 * @property Carbon $voucher_date
 * @property int $account_id
 * @property int $cash_account_id
 * @property int|null $department_id
 * @property string|null $payee
 * @property string $description
 * @property string|null $reference
 * @property string|null $cheque_no
 * @property Carbon|null $cheque_date
 * @property ChequeStatus|null $cheque_status
 * @property string $amount
 * @property string $tax_amount
 * @property VoucherStatus $status
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property-read Account $account
 * @property-read Account $cashAccount
 * @property-read JournalEntry|null $entry
 */
#[UseFactory(VoucherFactory::class)]
#[Fillable([
    'property_id', 'voucher_no', 'type', 'voucher_date', 'account_id', 'cash_account_id', 'department_id', 'payee', 'description', 'reference', 'cheque_no', 'cheque_date', 'cheque_status',
    'amount', 'tax_amount', 'status', 'journal_entry_id', 'created_by', 'voided_by', 'voided_at', 'void_reason',
])]
class Voucher extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;
    use HasAttachments;

    /** @use HasFactory<VoucherFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoucherType::class, 'status' => VoucherStatus::class, 'voucher_date' => 'date', 'cheque_date' => 'date', 'cheque_status' => ChequeStatus::class, 'amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
