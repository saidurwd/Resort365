<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\HR\Database\Factories\HolidayFactory;

/**
 * A public or company holiday at one property (Step 6.2). `repeats_yearly` makes it fall on the same month and day every
 * year, so national holidays are entered once.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property Carbon $holiday_date
 * @property bool $is_paid
 * @property bool $repeats_yearly
 */
#[UseFactory(HolidayFactory::class)]
#[Fillable(['property_id', 'name', 'holiday_date', 'is_paid', 'repeats_yearly'])]
class Holiday extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['holiday_date' => 'date', 'is_paid' => 'boolean', 'repeats_yearly' => 'boolean'];
    }
}
