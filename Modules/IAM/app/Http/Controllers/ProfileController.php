<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Actions\UpdatePreferences;
use Modules\IAM\Http\Requests\UpdatePreferencesRequest;
use Modules\IAM\Models\User;

/**
 * The signed-in user's own profile: details and password (posted to Fortify),
 * preferences, two-factor authentication and recent sign-ins.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $this->user($request);

        return view('iam::profile.show', [
            'user' => $user,
            'locales' => (array) config('app.available_locales'),
            'signIns' => $user->loginHistories()->latest('id')->limit(10)->get(),
        ]);
    }

    public function updatePreferences(UpdatePreferencesRequest $request, UpdatePreferences $updatePreferences): RedirectResponse|JsonResponse
    {
        $user = $updatePreferences->handle($this->user($request), $request->validated('locale'), $request->validated('theme'));

        if ($request->expectsJson()) {
            return response()->json(['locale' => $user->locale, 'theme' => $user->theme]);
        }

        return to_route('iam.profile.show')->with('success', __('Preferences saved.'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
