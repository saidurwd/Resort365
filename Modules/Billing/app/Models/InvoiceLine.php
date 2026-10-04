<?php

namespace Modules\Billing\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\InvoiceLineFactory;

/**
 * A line of an invoice, copied from its folio line at issue (never changed afterwards).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $invoice_id
 * @property int|null $folio_line_id
 * @property Carbon $service_date
 * @property string|null $charge_code
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string $tax_amount
 * @property string $total
 */
#[UseFactory(InvoiceLineFactory::class)]
#[Fillable(['property_id', 'invoice_id', 'folio_line_id', 'service_date', 'charge_code', 'description', 'quantity', 'unit_price', 'amount', 'tax_amount', 'total'])]
class InvoiceLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
