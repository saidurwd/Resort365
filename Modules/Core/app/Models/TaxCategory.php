<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Database\Factories\TaxCategoryFactory;

/**
 * A group of taxes applied together, e.g. "Room" = service charge + VAT. Other modules refer to
 * categories by id (rate plans, menu items, extra charges). Categories are deactivated, never deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
#[UseFactory(TaxCategoryFactory::class)]
#[Fillable(['code', 'name', 'description', 'is_active'])]
class TaxCategory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaxCategoryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Taxes in calculation order.
     *
     * @return BelongsToMany<Tax, $this>
     */
    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'tax_category_taxes')->orderBy('taxes.sort_order')->orderBy('taxes.id');
    }
}
