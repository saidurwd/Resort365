<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Billing\Database\Factories\InvoiceFactory;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\InvoiceStatus;

/**
 * A folio's invoice (ARCHITECTURE §5.9), issued at check-out with sequential numbers. It is a
 * frozen copy: lines, tax breakdown (tax name => amount), what was paid and what went on account
 * (city ledger). Only credit notes change what it is worth (credited, status); nothing else on it
 * may change once issued.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $folio_id
 * @property int|null $reservation_id
 * @property string $invoice_no
 * @property Carbon $issue_date
 * @property BillTo $bill_to_type
 * @property int|null $bill_to_id
 * @property string $bill_to_name
 * @property string|null $bill_to_tax_number
 * @property string $currency_code
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property string $paid
 * @property string $on_account
 * @property string $credited
 * @property array<string, string>|null $tax_breakdown
 * @property InvoiceStatus $status
 * @property int|null $issued_by
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, CreditNote> $creditNotes
 */
#[UseFactory(InvoiceFactory::class)]
#[Fillable([
    'property_id', 'folio_id', 'reservation_id', 'invoice_no', 'issue_date', 'bill_to_type', 'bill_to_id', 'bill_to_name', 'bill_to_tax_number',
    'currency_code', 'subtotal', 'tax_total', 'total', 'paid', 'on_account', 'credited', 'tax_breakdown', 'status', 'issued_by',
])]
class Invoice extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * Columns a credit note may change; everything else is frozen at issue.
     */
    public const array CREDIT_COLUMNS = ['credited', 'status', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if (array_diff(array_keys($invoice->getDirty()), self::CREDIT_COLUMNS) !== []) {
                throw new LogicException('An issued invoice cannot be changed; issue a credit note.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Invoices are never deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'bill_to_type' => BillTo::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'on_account' => 'decimal:2',
            'credited' => 'decimal:2',
            'tax_breakdown' => 'array',
            'status' => InvoiceStatus::class,
        ];
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('service_date')->orderBy('id');
    }

    /**
     * @return HasMany<CreditNote, $this>
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('id');
    }

    /**
     * What is still owed on the invoice: total less paid, on account and credited.
     */
    public function balance(): string
    {
        return bcsub(bcsub(bcsub($this->total, $this->paid, 2), $this->on_account, 2), $this->credited, 2);
    }
}
