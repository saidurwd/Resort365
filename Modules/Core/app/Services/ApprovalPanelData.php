<?php

namespace Modules\Core\Services;

use Illuminate\Support\Carbon;
use Modules\Core\Contracts\ApprovalParticipants;
use Modules\Core\Enums\ApprovalActionType;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Enums\ApprovalStepState;
use Modules\Core\Models\ApprovalAction;
use Modules\Core\Models\ApprovalRequest;

/**
 * The chain of a request as `<x-approval-panel>` shows it (Step 5.1): per frozen step its state, who decided
 * (and for whom, when delegated), when and with which comment.
 */
class ApprovalPanelData
{
    public function __construct(private readonly ApprovalParticipants $people) {}

    /**
     * @return list<array{level: int, role: string, approver: string|null, status: ApprovalStepState, acted_at: Carbon|null, comment: string|null}>
     */
    public function steps(ApprovalRequest $request): array
    {
        $actions = $request->actions;
        $names = $this->people->names(array_values(array_unique(array_filter([...$actions->pluck('user_id')->all(), ...$actions->pluck('on_behalf_of')->all()]))));
        $steps = [];

        foreach ($request->steps as $step) {
            $decided = $actions->first(fn (ApprovalAction $action): bool => $action->level === $step['level'] && in_array($action->action, [ApprovalActionType::Approved->value, ApprovalActionType::Rejected->value], true));
            $state = match (true) {
                $decided !== null => $decided->action === ApprovalActionType::Approved->value ? ApprovalStepState::Approved : ApprovalStepState::Rejected,
                $request->status === ApprovalStatus::Pending && $request->current_level === $step['level'] => ApprovalStepState::Pending,
                default => ApprovalStepState::Waiting,
            };
            $who = $decided !== null ? ($names[$decided->user_id] ?? '').($decided->on_behalf_of !== null ? ' '.__('for :name', ['name' => $names[$decided->on_behalf_of] ?? '']) : '') : null;
            $steps[] = ['level' => $step['level'], 'role' => $step['label'], 'approver' => $who, 'status' => $state, 'acted_at' => $decided?->acted_at, 'comment' => $decided?->comment];
        }

        return $steps;
    }
}
