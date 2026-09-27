<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\IAM\Models\User;

/**
 * Saves the user's language and colour mode (light, dark or auto).
 */
class UpdatePreferences extends Action
{
    public function handle(User $user, ?string $locale = null, ?string $theme = null): User
    {
        $user->forceFill(array_filter(['locale' => $locale, 'theme' => $theme], fn (?string $value): bool => $value !== null))->save();

        return $user;
    }
}
