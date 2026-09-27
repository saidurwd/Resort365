<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;

/**
 * Creates an invited user (no password yet) in the current tenant and emails the invitation.
 * Input is validated by InviteUserRequest (email unique per tenant).
 *
 * @phpstan-type Invitation array{user: User, url: string}
 */
class InviteUser extends Action
{
    public function __construct(private readonly SendInvitation $sendInvitation) {}

    /**
     * @return Invitation
     */
    public function handle(string $name, string $email, User $inviter): array
    {
        $user = new User(['name' => $name, 'email' => strtolower($email), 'status' => UserStatus::Invited]);
        $user->forceFill(['invited_by' => $inviter->id])->save();

        return ['user' => $user, 'url' => $this->sendInvitation->handle($user)];
    }
}
