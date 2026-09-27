<?php

namespace App\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\TenantModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A module switched on or off for a tenant. TODO(step-7.3): managed from Platform plans.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $module
 * @property bool $enabled
 */
#[UseFactory(TenantModuleFactory::class)]
#[Fillable(['module', 'enabled'])]
class TenantModule extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TenantModuleFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
