<?php

namespace Modules\Core\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Models\ApprovalAction;
use Modules\Core\Models\ApprovalRequest;

/**
 * Approval requests (Step 5.1): seen by who asked, who may decide now, who already did, and core.approval.manage;
 * decided by who may decide the current step; withdrawn by who asked (or core.approval.manage).
 */
class ApprovalRequestPolicy
{
    public function __construct(private readonly ApprovalEngine $engine) {}

    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('core.approval.view');
    }

    public function view(Authenticatable&Authorizable $user, ApprovalRequest $request): bool
    {
        $id = (int) $user->getAuthIdentifier();

        return $user->can('core.approval.manage') || ($user->can('core.approval.view') && ($request->requested_by === $id || $this->engine->canDecide($request->id, $id)
            || ApprovalAction::query()->where('approval_request_id', $request->id)->where('user_id', $id)->exists()));
    }

    public function decide(Authenticatable&Authorizable $user, ApprovalRequest $request): bool
    {
        return $this->engine->canDecide($request->id, (int) $user->getAuthIdentifier());
    }

    public function cancel(Authenticatable&Authorizable $user, ApprovalRequest $request): bool
    {
        return $request->requested_by === (int) $user->getAuthIdentifier() || $user->can('core.approval.manage');
    }
}
