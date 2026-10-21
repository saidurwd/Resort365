<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\PlanLimits;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Creates an invited user (no password yet) in the current tenant and emails the invitation. The user must fit the plan (Step 7.3).
 * Input is validated by InviteUserRequest (email unique per tenant).
 *
 * @phpstan-type Invitation array{user: User, url: string}
 */
class InviteUser extends Action
{
    public function __construct(
        private readonly SendInvitation $sendInvitation,
        private readonly AssignRoles $assignRoles,
        private readonly PlanLimits $limits,
    ) {}

    /**
     * @param  list<int>  $roleIds
     * @return Invitation
     */
    public function handle(string $name, string $email, User $inviter, array $roleIds = []): array
    {
        $this->limits->ensureWithin('max_users', User::query()->whereIn('status', [UserStatus::Invited->value, UserStatus::Active->value])->count() + 1);

        $user = $this->transaction(function () use ($name, $email, $inviter, $roleIds): User {
            $user = new User(['name' => $name, 'email' => strtolower($email), 'status' => UserStatus::Invited]);
            $user->forceFill(['invited_by' => $inviter->id])->save();
            $this->assignRoles->handle($user, $roleIds);

            return $user;
        });

        return ['user' => $user, 'url' => $this->sendInvitation->handle($user)];
    }
}
