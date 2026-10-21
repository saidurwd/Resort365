<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\PlanFactory;

/**
 * A plan tenants subscribe to (central, Step 7.3): what it costs, the limits it sets (empty = unlimited) and the modules it includes.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $monthly_price
 * @property string $yearly_price
 * @property string $currency_code
 * @property int|null $max_properties
 * @property int|null $max_rooms
 * @property int|null $max_users
 * @property int|null $storage_gb
 * @property list<string> $modules
 * @property bool $api_access
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 */
#[UseFactory(PlanFactory::class)]
#[Fillable(['code', 'name', 'description', 'monthly_price', 'yearly_price', 'currency_code', 'max_properties', 'max_rooms', 'max_users', 'storage_gb', 'modules', 'api_access', 'is_active', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2', 'yearly_price' => 'decimal:2', 'max_properties' => 'integer', 'max_rooms' => 'integer', 'max_users' => 'integer', 'storage_gb' => 'integer',
            'modules' => 'array', 'api_access' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer',
        ];
    }

    /** The most the plan allows for a limit (`max_properties`, `max_rooms`, `max_users`, `storage_gb`), or null for no limit. */
    public function limit(string $name): int|float|null
    {
        return in_array($name, ['max_properties', 'max_rooms', 'max_users', 'storage_gb'], true) ? $this->getAttribute($name) : null;
    }
}
