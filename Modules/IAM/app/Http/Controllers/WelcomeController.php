<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\IAM\Services\OwnerAccountsService;

/**
 * The owner of a company that has just signed up arrives here from the central domain (Step 7.4): the one-time link signs them in and
 * opens the setup checklist (after they confirm their email address).
 */
class WelcomeController extends Controller
{
    public function __invoke(Request $request, string $token, OwnerAccountsService $owners): RedirectResponse
    {
        $userId = $owners->redeem($token);
        $user = $userId !== null ? User::query()->find($userId) : null;

        if (! $user instanceof User || $user->status !== UserStatus::Active) {
            return redirect()->route('login')->with('status', __('This link has expired or was already used. Sign in with your email and password.'));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('core.onboarding.index');
    }
}
