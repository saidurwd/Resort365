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
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Database\Factories\SalaryStructureFactory;

/**
 * A template of salary components and how each is worked out (Step 6.4); employees are assigned one with a basic salary.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property-read Collection<int, SalaryStructureComponent> $lines
 */
#[UseFactory(SalaryStructureFactory::class)]
#[Fillable(['code', 'name', 'description', 'is_active'])]
class SalaryStructure extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SalaryStructureFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<SalaryStructureComponent, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureComponent::class)->orderBy('sort_order')->orderBy('id');
    }
}
