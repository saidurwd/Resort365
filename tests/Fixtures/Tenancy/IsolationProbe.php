<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Sample tenant-owned model for the isolation harness.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 */
#[UseFactory(IsolationProbeFactory::class)]
#[Fillable(['code', 'name'])]
class IsolationProbe extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<IsolationProbeFactory> */
    use HasFactory;
}
