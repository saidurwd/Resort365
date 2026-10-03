<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Database\Factories\TaxFactory;
use Modules\Core\Enums\TaxType;

/**
 * A tax or charge (VAT, service charge, tourism levy…). rate is a percentage for percent taxes
 * and an amount per unit for fixed ones (decimal string, 4 places).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property TaxType $type
 * @property string $rate
 * @property bool $is_compound
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $description
 */
#[UseFactory(TaxFactory::class)]
#[Fillable(['code', 'name', 'type', 'rate', 'is_compound', 'sort_order', 'is_active', 'description'])]
class Tax extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaxFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaxType::class,
            'rate' => 'decimal:4',
            'is_compound' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<TaxCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TaxCategory::class, 'tax_category_taxes');
    }

    /**
     * The rate for display: "15%" or "200.00".
     */
    public function rateLabel(): string
    {
        return $this->type === TaxType::Percent
            ? rtrim(rtrim($this->rate, '0'), '.').'%'
            : number_format((float) $this->rate, 2);
    }
}
