<?php

namespace Modules\IAM\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\IAM\Notifications\UserInvitation;

/**
 * Emails an invited user a signed link to set their password. Returns the link.
 */
class SendInvitation extends Action
{
    public const int EXPIRES_IN_DAYS = 7;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $user): string
    {
        if ($user->status !== UserStatus::Invited) {
            throw ValidationException::withMessages(['user' => __('Only invited users can be sent an invitation.')]);
        }

        $url = URL::temporarySignedRoute('iam.invitations.show', now()->addDays(self::EXPIRES_IN_DAYS), ['user' => $user->id]);

        $user->forceFill(['invited_at' => now()])->save();
        $user->notify(new UserInvitation($url, $this->context->tenantOrFail()->name, $user->inviter?->name, self::EXPIRES_IN_DAYS));

        return $url;
    }
}
