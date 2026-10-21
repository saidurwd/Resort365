<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\Enums\PlatformPaymentMethod;

/**
 * A payment a tenant made against a platform invoice (central, Step 7.4), recorded by a platform admin (or a gateway later).
 *
 * @property int $id
 * @property int $platform_invoice_id
 * @property int $tenant_id
 * @property string $amount
 * @property PlatformPaymentMethod $method
 * @property string|null $reference
 * @property Carbon $paid_on
 * @property int|null $recorded_by
 */
#[Fillable(['platform_invoice_id', 'tenant_id', 'amount', 'method', 'reference', 'paid_on', 'recorded_by'])]
class PlatformPayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'method' => PlatformPaymentMethod::class, 'paid_on' => 'date'];
    }

    /**
     * @return BelongsTo<PlatformInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PlatformInvoice::class, 'platform_invoice_id');
    }
}
