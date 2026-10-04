<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\CreditNoteFactory;

/**
 * A correction to an issued invoice (ARCHITECTURE §5.9): it first reduces what the company still
 * owes on the city ledger, the rest is refundable to whoever paid (refund_due, refunded).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $invoice_id
 * @property string $credit_note_no
 * @property Carbon $issue_date
 * @property string $amount
 * @property string $reason
 * @property string $applied_to_ledger
 * @property string $refund_due
 * @property string $refunded
 * @property int|null $issued_by
 * @property-read Invoice $invoice
 */
#[UseFactory(CreditNoteFactory::class)]
#[Fillable(['property_id', 'invoice_id', 'credit_note_no', 'issue_date', 'amount', 'reason', 'applied_to_ledger', 'refund_due', 'refunded', 'issued_by'])]
class CreditNote extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CreditNoteFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'amount' => 'decimal:2',
            'applied_to_ledger' => 'decimal:2',
            'refund_due' => 'decimal:2',
            'refunded' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
