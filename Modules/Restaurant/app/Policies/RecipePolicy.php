<?php

namespace Modules\Restaurant\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Recipes, sub-recipes and food costing: restaurant.recipe.view to look, restaurant.recipe.manage to change.
 */
class RecipePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.recipe.view');
    }

    public function view(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('restaurant.recipe.view');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('restaurant.recipe.manage');
    }

    public function update(Authenticatable&Authorizable $user, mixed $model = null): bool
    {
        return $user->can('restaurant.recipe.manage');
    }
}
