<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits property-level records to the properties the signed-in user may access.
 * Unrestricted without a user (console, jobs).
 *
 * @implements Scope<Model>
 */
class PropertyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $ids = app(PropertyContext::class)->accessibleIds();

        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('property_id'), $ids);
        }
    }
}
