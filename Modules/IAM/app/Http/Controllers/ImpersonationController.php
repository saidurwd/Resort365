<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\Impersonation;
use Modules\Core\DTOs\ImpersonationGrant;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * A platform admin signing in as a tenant user and leaving again (Step 7.3, audited). The link comes from the platform console, works
 * once for a minute and is for this tenant only; the session then carries a banner and tags what is done with the admin's name.
 */
class ImpersonationController extends Controller
{
    public const string SESSION_KEY = 'impersonation';

    public function enter(Request $request, string $token, Impersonation $impersonation): RedirectResponse
    {
        $grant = $impersonation->redeem($token);
        $user = $grant instanceof ImpersonationGrant ? User::query()->find($grant->userId) : null;

        if (! $grant instanceof ImpersonationGrant || ! $user instanceof User || $user->status !== UserStatus::Active) {
            abort(403, __('This sign-in link has expired or was already used.'));
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, ['session_id' => $grant->sessionId, 'admin_id' => $grant->adminId, 'admin_name' => $grant->adminName, 'user_name' => $user->name]);

        return redirect()->route('dashboard');
    }

    public function leave(Request $request, Impersonation $impersonation): RedirectResponse
    {
        $state = $request->session()->get(self::SESSION_KEY);

        if (is_array($state)) {
            $impersonation->end((int) $state['session_id']);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away($impersonation->exitUrl());
    }
}
