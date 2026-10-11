<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\ApprovalParticipants;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalExecutionFailed;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Http\Requests\ApprovalDecisionRequest;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Services\ApprovalPanelData;

/**
 * Approvals: what waits for me, what I asked for, and a request with its chain and the decision buttons.
 */
class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalEngine $engine, ApprovalParticipants $people): View
    {
        Gate::authorize('viewAny', ApprovalRequest::class);
        $user = (int) $request->user()?->getAuthIdentifier();
        $pending = ApprovalRequest::query()->where('status', ApprovalStatus::Pending->value)->orderBy('id')->get();
        $mine = ApprovalRequest::query()->where('requested_by', $user)->orderByDesc('id')->limit(50)->get();
        $all = $request->user()?->can('core.approval.manage') ? ApprovalRequest::query()->orderByDesc('id')->limit(100)->get() : collect();
        $delegations = ApprovalDelegation::query()->where('user_id', $user)->where('ends_on', '>=', now()->toDateString())->orderBy('starts_on')->get();
        $colleagues = $people->colleagues();

        return view('core::approvals.index', [
            'waiting' => $pending->filter(fn (ApprovalRequest $row): bool => $engine->canDecide($row->id, $user))->values(), 'mine' => $mine, 'all' => $all, 'names' => $colleagues, 'delegations' => $delegations, 'colleagues' => array_diff_key($colleagues, [$user => true]),
            'types' => collect(app(ApprovalRegistry::class)->types())->map(fn ($type): string => $type->label)->all(),
        ]);
    }

    public function show(ApprovalRequest $approval, ApprovalPanelData $panel, ApprovalParticipants $people, Request $request): View
    {
        Gate::authorize('view', $approval);
        $approval->load('actions');
        $user = (int) $request->user()?->getAuthIdentifier();
        $types = app(ApprovalRegistry::class)->types();

        return view('core::approvals.show', [
            'approval' => $approval, 'steps' => $panel->steps($approval), 'canDecide' => $approval->status === ApprovalStatus::Pending && Gate::allows('decide', $approval),
            'canCancel' => $approval->status === ApprovalStatus::Pending && Gate::allows('cancel', $approval), 'typeLabel' => isset($types[$approval->document_type]) ? $types[$approval->document_type]->label : $approval->document_type,
            'requester' => $people->names([$approval->requested_by])[$approval->requested_by] ?? '', 'mine' => $approval->requested_by === $user,
        ]);
    }

    public function approve(ApprovalDecisionRequest $request, ApprovalRequest $approval, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('decide', $approval);

        try {
            $summary = $engine->approve($approval->id, (int) $request->user()?->getAuthIdentifier(), $request->input('comment'));
        } catch (ApprovalRuleViolated|ApprovalExecutionFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('core.approvals.show', $approval)->with('success', $summary->status === ApprovalStatus::Approved ? __('Approved.') : __('Approved: it now waits for :step.', ['step' => (string) $summary->currentStep]));
    }

    public function reject(ApprovalDecisionRequest $request, ApprovalRequest $approval, ApprovalEngine $engine): RedirectResponse
    {
        Gate::authorize('decide', $approval);

        try {
            $engine->reject($approval->id, (int) $request->user()?->getAuthIdentifier(), (string) $request->input('comment'));
        } catch (ApprovalRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('core.approvals.show', $approval)->with('success', __('Rejected.'));
    }

    public function cancel(ApprovalRequest $approval, ApprovalEngine $engine, Request $request): RedirectResponse
    {
        Gate::authorize('cancel', $approval);

        try {
            $engine->cancel($approval->id, (int) $request->user()?->getAuthIdentifier());
        } catch (ApprovalRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('core.approvals.show', $approval)->with('success', __('Request withdrawn.'));
    }
}
