<?php

namespace Modules\IAM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\IAM\Actions\ActivateUser;
use Modules\IAM\Actions\AssignRoles;
use Modules\IAM\Actions\DeactivateUser;
use Modules\IAM\Actions\InviteUser;
use Modules\IAM\Actions\SendInvitation;
use Modules\IAM\Http\Requests\InviteUserRequest;
use Modules\IAM\Http\Requests\UpdateUserRolesRequest;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;
use Modules\IAM\Services\UsersTable;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', User::class);

        return view('iam::users.index', ['columns' => UsersTable::columns(), 'roles' => $this->roleOptions()]);
    }

    public function data(Request $request, UsersTable $table): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        return $table->toJson($this->actor($request));
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $invitation = $inviteUser->handle(
            (string) $request->string('name'),
            (string) $request->string('email'),
            $this->actor($request),
            array_map(intval(...), (array) $request->validated('roles')),
        );

        return to_route('iam.users.index')->with('success', $this->sentMessage($invitation['user'], $invitation['url']));
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('iam::users.edit', [
            'user' => $user,
            'roles' => $this->roleOptions(),
            'assigned' => $user->roles()->pluck('roles.id')->all(),
        ]);
    }

    public function updateRoles(UpdateUserRolesRequest $request, User $user, AssignRoles $assignRoles): RedirectResponse
    {
        $assignRoles->handle($user, array_map(intval(...), (array) $request->validated('roles')));

        return to_route('iam.users.index')->with('success', __('Roles of :name saved.', ['name' => $user->name]));
    }

    public function resendInvitation(User $user, SendInvitation $sendInvitation): RedirectResponse
    {
        Gate::authorize('update', $user);

        $url = $sendInvitation->handle($user);

        return to_route('iam.users.index')->with('success', $this->sentMessage($user, $url));
    }

    public function activate(User $user, ActivateUser $activateUser): RedirectResponse
    {
        Gate::authorize('update', $user);

        $activateUser->handle($user);

        return to_route('iam.users.index')->with('success', __(':name can sign in again.', ['name' => $user->name]));
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        Gate::authorize('deactivate', $user);

        $deactivateUser->handle($user, $this->actor($request));

        return to_route('iam.users.index')->with('success', __(':name has been deactivated.', ['name' => $user->name]));
    }

    /**
     * @return array<int, string> role id => label
     */
    private function roleOptions(): array
    {
        return Role::query()->orderByDesc('is_system')->orderBy('id')->get()
            ->mapWithKeys(fn (Role $role): array => [$role->id => $role->label()])
            ->all();
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Locally, the link is shown too, because mail only goes to the log.
     */
    private function sentMessage(User $user, string $url): string
    {
        $message = __('Invitation sent to :email.', ['email' => $user->email]);

        return app()->isLocal() ? $message.' '.__('Local link: :url', ['url' => $url]) : $message;
    }
}
