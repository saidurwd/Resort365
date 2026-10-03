<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Sample property-level model for the property-access harness.
 *
 * @property int $id
 * @property int $property_id
 * @property string $name
 */
#[UseFactory(PropertyProbeFactory::class)]
#[Fillable(['property_id', 'name'])]
class PropertyProbe extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PropertyProbeFactory> */
    use HasFactory;
}
