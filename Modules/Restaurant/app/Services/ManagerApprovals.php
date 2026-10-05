<?php

namespace Modules\Restaurant\Services;

use Illuminate\Support\Facades\RateLimiter;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\ManagerApproval;
use Modules\Restaurant\Models\PosTerminal;

/**
 * Manager PIN approval on a shared terminal (ARCHITECTURE §3.3 rule 6): a manager who holds the
 * action's permission and works in the outlet enters their PIN; the approval is recorded against
 * them, valid for 5 minutes, and used once by the action it was asked for (same action, same
 * subject, same staff member). Five wrong PINs for a manager lock their approvals on that terminal
 * for 15 minutes.
 */
class ManagerApprovals
{
    public const int VALID_MINUTES = 5;

    /**
     * The actions that can be approved by a manager's PIN: action => [permission it needs, subject type].
     * Later steps add voids and discounts.
     *
     * @var array<string, array{permission: string, subject: string|null, label: string}>
     */
    public const array ACTIONS = [
        'session.close-variance' => ['permission' => 'restaurant.session.approve-variance', 'subject' => 'pos_session', 'label' => 'Close a session with a large cash difference'],
    ];

    private const int ATTEMPTS = 5;

    public function __construct(
        private readonly PosPins $pins,
        private readonly UserDirectory $users,
        private readonly OutletAccess $outlets,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function approve(PosTerminal $terminal, string $action, string $permission, int $managerId, string $pin, int $requestedBy,
        ?string $subjectType = null, ?int $subjectId = null, ?string $reason = null): ManagerApproval
    {
        $key = 'pos-approval:'.$terminal->id.':'.$managerId;

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw new PosNotAllowed(__('Too many wrong PINs. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
        }

        if (! $this->pins->matches($managerId, $pin)) {
            RateLimiter::hit($key, 15 * 60);

            throw new PosNotAllowed(__('Wrong PIN.'));
        }

        RateLimiter::clear($key);

        if ($managerId === $requestedBy || ! $this->users->userCan($managerId, $permission) || ! $this->outlets->canUse($managerId, $terminal->outlet_id)) {
            throw new PosNotAllowed(__('This person cannot approve it.'));
        }

        return ManagerApproval::query()->create([
            'property_id' => $terminal->property_id, 'outlet_id' => $terminal->outlet_id, 'pos_terminal_id' => $terminal->id, 'action' => $action,
            'permission' => $permission, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'requested_by' => $requestedBy, 'approved_by' => $managerId,
            'reason' => $reason, 'expires_at' => now()->addMinutes(self::VALID_MINUTES),
        ]);
    }

    /**
     * The people who could approve an action on this terminal: they hold its permission, work in the
     * outlet and have a POS PIN.
     *
     * @return array<int, string> user id => name
     */
    public function approvers(PosTerminal $terminal, string $action, int $except): array
    {
        $permission = self::ACTIONS[$action]['permission'] ?? null;

        return $permission === null ? [] : collect($this->users->all())
            ->filter(fn ($user): bool => $user->id !== $except && $user->status === 'active' && $this->pins->has($user->id)
                && $this->users->userCan($user->id, $permission) && $this->outlets->canUse($user->id, $terminal->outlet_id))
            ->sortBy('name')->mapWithKeys(fn ($user): array => [$user->id => $user->name])->all();
    }

    /**
     * Uses an approval for the action it was given for; call it inside the action's transaction.
     *
     * @throws PosNotAllowed
     */
    public function consume(int $approvalId, string $action, int $requestedBy, ?string $subjectType = null, ?int $subjectId = null): ManagerApproval
    {
        $approval = ManagerApproval::query()->lockForUpdate()->find($approvalId);

        if (! $approval instanceof ManagerApproval || $approval->used_at !== null || $approval->expires_at->isPast() || $approval->action !== $action
            || $approval->requested_by !== $requestedBy || $approval->subject_type !== $subjectType || $approval->subject_id !== $subjectId) {
            throw new PosNotAllowed(__('The manager\'s approval is missing or no longer valid; ask again.'));
        }

        $approval->forceFill(['used_at' => now()])->save();

        return $approval;
    }
}
