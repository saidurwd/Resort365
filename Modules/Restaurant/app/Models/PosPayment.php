<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PosPaymentFactory;
use Modules\Restaurant\Enums\PaymentMethod;

/**
 * A payment on a restaurant bill (ARCHITECTURE §5.10.7, §8.4), taken in a POS session on the business
 * date: amount towards the bill, the tip on top, and for cash what was tendered and the change. A
 * refund (a voided bill) is a negative payment pointing at the one it refunds. A room charge points at
 * the folio line it posted; a city-ledger payment at the company's account entry (Step 3.7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $pos_bill_id
 * @property int|null $pos_session_id
 * @property Carbon $business_date
 * @property PaymentMethod $method
 * @property string $amount
 * @property string $tip
 * @property string|null $tendered
 * @property string $change_given
 * @property string|null $reference
 * @property int|null $refund_of_id
 * @property int|null $reservation_id charged to this booking's folio
 * @property int|null $folio_id
 * @property int|null $folio_line_id
 * @property int|null $company_id billed to this company's account
 * @property int|null $city_ledger_entry_id
 * @property string|null $charged_to who it was charged to, for the receipt
 * @property string|null $signature_path the guest's signature (TenantStorage)
 * @property int|null $created_by
 */
#[UseFactory(PosPaymentFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_bill_id', 'pos_session_id', 'business_date', 'method', 'amount', 'tip', 'tendered', 'change_given', 'reference',
    'refund_of_id', 'created_by', 'reservation_id', 'folio_id', 'folio_line_id', 'company_id', 'city_ledger_entry_id', 'charged_to', 'signature_path',
])]
class PosPayment extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosPaymentFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'tip' => 'decimal:2',
            'tendered' => 'decimal:2',
            'change_given' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PosBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(PosBill::class, 'pos_bill_id');
    }
}
