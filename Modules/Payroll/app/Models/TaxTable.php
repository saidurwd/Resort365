<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Payroll\Database\Factories\TaxTableFactory;

/**
 * The income tax rules of a tax year as data (Step 6.4): the tax-free amount for each taxpayer category, the salary
 * exemption (a fraction of salary up to a cap), the minimum tax for each tax area, and the slabs (`tax_slabs`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $tax_year
 * @property Carbon $effective_from
 * @property list<array{code: string, label: string, amount: string}> $thresholds
 * @property list<array{code: string, label: string, amount: string}> $minimum_tax
 * @property string $exemption_fraction a decimal such as 0.25 or a fraction such as 1/3
 * @property string $exemption_cap
 * @property bool $is_active
 * @property-read Collection<int, TaxSlab> $slabs
 */
#[UseFactory(TaxTableFactory::class)]
#[Fillable(['name', 'tax_year', 'effective_from', 'thresholds', 'minimum_tax', 'exemption_fraction', 'exemption_cap', 'is_active'])]
class TaxTable extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaxTableFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'thresholds' => 'array', 'minimum_tax' => 'array', 'exemption_cap' => 'decimal:2', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<TaxSlab, $this>
     */
    public function slabs(): HasMany
    {
        return $this->hasMany(TaxSlab::class)->orderBy('sort_order');
    }
}
