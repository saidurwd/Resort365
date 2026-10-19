<?php

namespace Modules\HR\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\HR\Models\LeaveRequest;

/**
 * Leave requests: hr.leave.view sees every request, an employee sees their own; hr.leave.request asks for one's own leave
 * and hr.leave.manage for anyone's, and cancels anyone's; an employee cancels their own.
 */
class LeaveRequestPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave.view');
    }

    public function view(Authenticatable&Authorizable $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave.view') || $this->isOwn($user, $request);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('hr.leave.request') || $user->can('hr.leave.manage');
    }

    public function cancel(Authenticatable&Authorizable $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave.manage') || ($user->can('hr.leave.request') && $this->isOwn($user, $request));
    }

    public function viewAttachments(Authenticatable&Authorizable $user, LeaveRequest $request): bool
    {
        return $this->view($user, $request);
    }

    public function update(Authenticatable&Authorizable $user, LeaveRequest $request): bool
    {
        return $this->cancel($user, $request);
    }

    private function isOwn(Authenticatable&Authorizable $user, LeaveRequest $request): bool
    {
        return $request->employee->user_id !== null && $request->employee->user_id === $user->getAuthIdentifier();
    }
}
