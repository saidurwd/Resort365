<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ExchangeRateFactory;

/**
 * 1 unit of base_currency = rate units of quote_currency, from effective_date.
 *
 * @property int $id
 * @property string $base_currency
 * @property string $quote_currency
 * @property string $rate decimal(18,8) as string
 * @property Carbon $effective_date
 * @property string|null $source
 */
#[UseFactory(ExchangeRateFactory::class)]
#[Fillable(['base_currency', 'quote_currency', 'rate', 'effective_date', 'source'])]
class ExchangeRate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'effective_date' => 'date',
        ];
    }
}
