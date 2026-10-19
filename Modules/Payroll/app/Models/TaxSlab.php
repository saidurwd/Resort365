<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\TaxSlabFactory;

/**
 * A band of taxable income above the tax-free amount (Step 6.4): its width (empty for the last band, which has no top) and
 * the rate charged on it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $tax_table_id
 * @property int $sort_order
 * @property string|null $width
 * @property string $rate
 */
#[UseFactory(TaxSlabFactory::class)]
#[Fillable(['tax_table_id', 'sort_order', 'width', 'rate'])]
class TaxSlab extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TaxSlabFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'width' => 'decimal:2', 'rate' => 'decimal:2'];
    }
}
