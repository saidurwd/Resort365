<?php

namespace Modules\Property\Support;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * A signed-in user only ever sees the properties they may access (lists, URLs, pickers).
 *
 * @implements Scope<Model>
 */
class AccessiblePropertyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $ids = app(PropertyContext::class)->accessibleIds();

        if ($ids !== null) {
            $builder->whereIn($model->qualifyColumn('id'), $ids);
        }
    }
}
