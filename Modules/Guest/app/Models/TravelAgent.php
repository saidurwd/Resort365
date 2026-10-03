<?php

namespace Modules\Guest\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Guest\Database\Factories\TravelAgentFactory;

/**
 * A travel agent that sends bookings (ARCHITECTURE §5.8): commission % and credit limit
 * (tenant base currency).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property array<string, string|null>|null $address
 * @property string $commission_percent
 * @property string $credit_limit
 * @property bool $is_active
 * @property string|null $notes
 */
#[UseFactory(TravelAgentFactory::class)]
#[Fillable(['code', 'name', 'contact_person', 'email', 'phone', 'address', 'commission_percent', 'credit_limit', 'is_active', 'notes'])]
class TravelAgent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TravelAgentFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'commission_percent' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
