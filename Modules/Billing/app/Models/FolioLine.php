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
use Modules\Billing\Database\Factories\FolioLineFactory;
use Modules\Billing\Enums\FolioLineType;

/**
 * One line of a folio. Lines are never deleted or changed: a mistake is voided with a reason (and
 * stays visible) or corrected with an adjustment. total = amount + tax_amount, always positive
 * except for credit adjustments; FolioLineType::sign() says whether it adds to the balance.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $folio_id
 * @property Carbon $posting_date
 * @property FolioLineType $line_type
 * @property int|null $charge_code_id
 * @property int|null $extra_service_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string $tax_amount
 * @property array<string, string>|null $tax_lines tax name => amount
 * @property string $total
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property bool $revenue_posted_by_source
 * @property int|null $routed_from_folio_id
 * @property bool $is_voided
 * @property int|null $voided_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $posted_by
 * @property Carbon|null $created_at
 * @property-read ChargeCode|null $chargeCode
 * @property-read Folio $folio
 */
#[UseFactory(FolioLineFactory::class)]
#[Fillable([
    'property_id', 'folio_id', 'posting_date', 'line_type', 'charge_code_id', 'extra_service_id', 'description', 'quantity', 'unit_price',
    'amount', 'tax_amount', 'tax_lines', 'total', 'reference_type', 'reference_id', 'revenue_posted_by_source', 'routed_from_folio_id', 'posted_by',
])]
class FolioLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<FolioLineFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'line_type' => FolioLineType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_lines' => 'array',
            'total' => 'decimal:2',
            'revenue_posted_by_source' => 'boolean',
            'is_voided' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChargeCode, $this>
     */
    public function chargeCode(): BelongsTo
    {
        return $this->belongsTo(ChargeCode::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }
}
