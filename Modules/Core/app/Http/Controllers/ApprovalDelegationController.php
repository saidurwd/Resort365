<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Modules\Core\Actions\SaveApprovalDelegation;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Http\Requests\ApprovalDelegationRequest;
use Modules\Core\Models\ApprovalDelegation;

/**
 * Approvals → my delegation: let a colleague decide in my place for a while, or end it.
 */
class ApprovalDelegationController extends Controller
{
    public function store(ApprovalDelegationRequest $request, SaveApprovalDelegation $save): RedirectResponse
    {
        try {
            $save->handle((int) $request->user()?->getAuthIdentifier(), (int) $request->validated('delegate_user_id'), (string) $request->validated('starts_on'), (string) $request->validated('ends_on'));
        } catch (ApprovalRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('core.approvals.index')->with('success', __('Delegation saved.'));
    }

    public function destroy(ApprovalDelegation $delegation): RedirectResponse
    {
        abort_unless($delegation->user_id === (int) auth()->id(), 403);
        $delegation->delete();

        return to_route('core.approvals.index')->with('success', __('Delegation ended.'));
    }
}
