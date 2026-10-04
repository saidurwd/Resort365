<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\MenuScheduleFactory;

/**
 * When part of an outlet's menu is sold (ARCHITECTURE §5.10.2): weekdays and a time window (a window that
 * ends before it starts runs past midnight), with a price adjustment in % (e.g. −20 for happy hour).
 * MenuAvailability applies it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property string $name
 * @property list<string> $days_of_week
 * @property string $start_time
 * @property string $end_time
 * @property string $price_adjustment_percent
 * @property bool $is_active
 */
#[UseFactory(MenuScheduleFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'name', 'days_of_week', 'start_time', 'end_time', 'price_adjustment_percent', 'is_active',
])]
class MenuSchedule extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MenuScheduleFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'price_adjustment_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
