<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * For property-level models (ARCHITECTURE §4.2, §12.1); use together with BelongsToTenant.
 *
 * - Queries only return records of properties the signed-in user may access (PropertyScope).
 * - `property_id` is filled from the current property on create.
 * - Records of a property the user may not access cannot be created, updated or deleted.
 *
 * Screens that show "this property's" data add ->where('property_id', PropertyContext::currentId()).
 *
 * @phpstan-require-extends Model
 */
trait BelongsToProperty
{
    public static function bootBelongsToProperty(): void
    {
        static::addGlobalScope(new PropertyScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('property_id') === null) {
                $model->setAttribute('property_id', app(PropertyContext::class)->currentId());
            }

            self::guardProperty($model);
        });

        static::updating(function (Model $model): void {
            self::guardProperty($model);

            if ($model->isDirty('property_id')) {
                $original = $model->getOriginal('property_id');
                self::guardPropertyId($model, is_numeric($original) ? (int) $original : 0);
            }
        });

        static::deleting(fn (Model $model) => self::guardProperty($model));
    }

    private static function guardProperty(Model $model): void
    {
        self::guardPropertyId($model, (int) $model->getAttribute('property_id'));
    }

    private static function guardPropertyId(Model $model, int $propertyId): void
    {
        if (! app(PropertyContext::class)->canAccess($propertyId)) {
            throw PropertyAccessDenied::forModel($model::class, $propertyId);
        }
    }
}
