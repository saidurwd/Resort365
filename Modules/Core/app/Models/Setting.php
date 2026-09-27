<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\SettingFactory;

/**
 * A stored setting value: tenant level (property_id null) or a property override.
 * Read and written through Core's Settings service, which casts values to their type.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $property_id
 * @property string $key
 * @property mixed $value
 */
#[UseFactory(SettingFactory::class)]
#[Fillable(['property_id', 'key', 'value'])]
class Setting extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
