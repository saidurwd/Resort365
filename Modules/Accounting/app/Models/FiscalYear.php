<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\FiscalYearFactory;

/**
 * A fiscal year of the tenant, with its monthly periods (ARCHITECTURE §5.14).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
#[UseFactory(FiscalYearFactory::class)]
#[Fillable(['name', 'starts_on', 'ends_on'])]
class FiscalYear extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FiscalYearFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    /**
     * @return HasMany<FiscalPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class)->orderBy('number');
    }
}
