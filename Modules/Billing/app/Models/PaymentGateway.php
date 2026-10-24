<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Database\Factories\PaymentGatewayFactory;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;

/**
 * A property's payment gateway (Step 8.3): the provider, sandbox or live, and its credentials (encrypted, never logged or shown
 * again after saving).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property GatewayProvider $provider
 * @property GatewayMode $mode
 * @property array<string, string>|null $credentials
 * @property bool $is_enabled
 * @property int $sort_order
 */
#[UseFactory(PaymentGatewayFactory::class)]
#[Fillable(['property_id', 'provider', 'mode', 'credentials', 'is_enabled', 'sort_order'])]
#[Hidden(['credentials'])]
class PaymentGateway extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PaymentGatewayFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['provider' => GatewayProvider::class, 'mode' => GatewayMode::class, 'credentials' => 'encrypted:array', 'is_enabled' => 'boolean', 'sort_order' => 'integer'];
    }

    public function setup(): GatewaySetup
    {
        return new GatewaySetup($this->id, $this->provider, $this->mode, array_map(strval(...), $this->credentials ?? []));
    }

    /** Whether every credential the provider needs is filled in. */
    public function isConfigured(): bool
    {
        return collect(array_keys($this->provider->credentialFields()))->every(fn (string $field): bool => trim((string) ($this->credentials[$field] ?? '')) !== '');
    }
}
