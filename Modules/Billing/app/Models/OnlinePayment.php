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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\OnlinePaymentFactory;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Enums\OnlinePaymentStatus;

/**
 * One attempt to pay a booking online (Step 8.3): how much, through which gateway, its state, the gateway's references and, once
 * paid, the Billing payment it became. Guests and gateways know it by `uuid`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $uuid
 * @property int $payment_gateway_id
 * @property GatewayProvider $provider
 * @property int $reservation_id
 * @property OnlinePaymentPurpose $purpose
 * @property string $amount
 * @property string $currency_code
 * @property OnlinePaymentStatus $status
 * @property string|null $gateway_ref
 * @property string|null $transaction_id
 * @property int|null $payment_id
 * @property string $refunded_amount
 * @property string|null $return_url
 * @property string|null $failure_reason
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property-read PaymentGateway $gateway
 */
#[UseFactory(OnlinePaymentFactory::class)]
#[Fillable(['property_id', 'uuid', 'payment_gateway_id', 'provider', 'reservation_id', 'purpose', 'amount', 'currency_code', 'status', 'gateway_ref',
    'transaction_id', 'payment_id', 'refunded_amount', 'return_url', 'failure_reason', 'expires_at', 'paid_at'])]
class OnlinePayment extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<OnlinePaymentFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => GatewayProvider::class, 'purpose' => OnlinePaymentPurpose::class, 'status' => OnlinePaymentStatus::class,
            'amount' => 'decimal:2', 'refunded_amount' => 'decimal:2', 'expires_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * @return HasMany<GatewayEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GatewayEvent::class);
    }

    public function isExpired(): bool
    {
        return $this->status === OnlinePaymentStatus::Pending && $this->expires_at !== null && $this->expires_at->isPast();
    }
}
