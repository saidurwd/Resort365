<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Actions\UpdatePreferences;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Exceptions\PosPinNotAllowed;
use Modules\IAM\Http\Requests\UpdatePosPinRequest;
use Modules\IAM\Http\Requests\UpdatePreferencesRequest;
use Modules\IAM\Models\User;

/**
 * The signed-in user's own profile: details and password (posted to Fortify),
 * preferences, the POS PIN, two-factor authentication and recent sign-ins.
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

    public function updatePosPin(UpdatePosPinRequest $request, PosPins $pins): RedirectResponse
    {
        $user = $this->user($request);

        if ($request->boolean('remove')) {
            $pins->clear($user->id);

            return redirect()->to(route('iam.profile.show').'#pos-pin')->with('success', __('POS PIN removed.'));
        }

        try {
            $pins->set($user->id, (string) $request->validated('pin'));
        } catch (PosPinNotAllowed $exception) {
            return redirect()->to(route('iam.profile.show').'#pos-pin')->withErrors(['pin' => $exception->getMessage()], 'posPin');
        }

        return redirect()->to(route('iam.profile.show').'#pos-pin')->with('success', __('POS PIN saved.'));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
