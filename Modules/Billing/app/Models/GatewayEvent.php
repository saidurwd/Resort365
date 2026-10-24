<?php

namespace Modules\Billing\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\GatewayEventFactory;
use Modules\Billing\Enums\GatewayProvider;

/**
 * The gateway log (Step 8.3): a start, return, notification (IPN, webhook) or refund, whether its signature was valid, what came of
 * it, and the payload without secrets. `event_id` (the gateway's own id for a notification) is unique, so a replay is recognised.
 * Append-only.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $online_payment_id
 * @property GatewayProvider $provider
 * @property string $kind
 * @property string|null $event_id
 * @property bool|null $signature_valid
 * @property string|null $outcome
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
#[UseFactory(GatewayEventFactory::class)]
#[Fillable(['property_id', 'online_payment_id', 'provider', 'kind', 'event_id', 'signature_valid', 'outcome', 'payload'])]
class GatewayEvent extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<GatewayEventFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['provider' => GatewayProvider::class, 'signature_valid' => 'boolean', 'payload' => 'array'];
    }
}
