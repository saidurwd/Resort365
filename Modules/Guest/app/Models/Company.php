<?php

namespace Modules\Guest\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Guest\Database\Factories\CompanyFactory;

/**
 * A corporate client (ARCHITECTURE §5.8). The credit limit is in the tenant's base currency.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $tax_number
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property array<string, string|null>|null $address
 * @property string $credit_limit
 * @property int $payment_terms_days
 * @property bool $is_active
 * @property string|null $notes
 */
#[UseFactory(CompanyFactory::class)]
#[Fillable(['name', 'legal_name', 'tax_number', 'contact_person', 'email', 'phone', 'address', 'credit_limit', 'payment_terms_days', 'is_active', 'notes'])]
class Company extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CompanyFactory> */
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
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }
}
