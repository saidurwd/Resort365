<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\IAM\Actions\AcceptInvitation;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Http\Requests\AcceptInvitationRequest;
use Modules\IAM\Models\User;

/**
 * Accepting an emailed invitation. Routes require a valid, unexpired signature.
 */
class InvitationController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if ($user->status !== UserStatus::Invited) {
            return to_route('login')->with('info', __('This invitation has already been used. Please sign in.'));
        }

        return view('iam::invitations.accept', ['user' => $user]);
    }

    public function store(AcceptInvitationRequest $request, User $user, AcceptInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->handle($user, (string) $request->string('name'), (string) $request->string('password'));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', __('Welcome, :name! Your account is ready.', ['name' => $user->name]));
    }
}
