<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\PaymentFactory;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Enums\RefundKind;

/**
 * Money received for a reservation (ARCHITECTURE §5.9, §8.3), recorded by RecordPayment with a
 * receipt number. Payments are never deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $receipt_no
 * @property int|null $reservation_id
 * @property int|null $folio_id
 * @property PaymentType $payment_type
 * @property PaymentMethod $method
 * @property string $amount
 * @property string $currency_code
 * @property string $exchange_rate
 * @property string $base_amount
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $gateway
 * @property string|null $gateway_txn_id
 * @property PaymentStatus $status
 * @property int|null $received_by
 * @property Carbon $received_at
 * @property int|null $cash_account_id
 * @property string|null $reason
 * @property RefundKind|null $refund_kind
 * @property int|null $refunded_payment_id
 * @property int|null $credit_note_id
 * @property int|null $city_ledger_entry_id
 * @property Carbon|null $business_date
 * @property int|null $cashier_shift_id
 */
#[UseFactory(PaymentFactory::class)]
#[Fillable([
    'property_id', 'receipt_no', 'reservation_id', 'folio_id', 'payment_type', 'method', 'amount', 'currency_code', 'exchange_rate',
    'base_amount', 'reference', 'notes', 'gateway', 'gateway_txn_id', 'status', 'received_by', 'received_at', 'cash_account_id',
    'reason', 'refund_kind', 'refunded_payment_id', 'credit_note_id', 'city_ledger_entry_id', 'business_date', 'cashier_shift_id',
])]
class Payment extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_type' => PaymentType::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:2',
            'received_at' => 'datetime',
            'business_date' => 'date',
            'refund_kind' => RefundKind::class,
        ];
    }
}
