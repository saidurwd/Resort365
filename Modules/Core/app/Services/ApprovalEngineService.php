<?php

namespace Modules\Core\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\ApprovalParticipants;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\DTOs\ApprovalDecision;
use Modules\Core\DTOs\ApprovalSummary;
use Modules\Core\Enums\ApprovalActionType;
use Modules\Core\Enums\ApprovalStatus;
use Modules\Core\Exceptions\ApprovalRuleViolated;
use Modules\Core\Models\ApprovalAction;
use Modules\Core\Models\ApprovalDelegation;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\ApprovalWorkflow;
use Modules\Core\Notifications\ApprovalNotification;

class ApprovalEngineService implements ApprovalEngine
{
    public function __construct(
        private readonly ApprovalRegistry $registry,
        private readonly ApprovalParticipants $people,
    ) {}

    public function submit(string $documentType, string $amount, string $reference, int $requestedBy, ?array $payload = null, ?int $propertyId = null, ?string $subjectType = null, ?int $subjectId = null): ?ApprovalSummary
    {
        $workflow = ApprovalWorkflow::query()->with('steps')->where('document_type', $documentType)->where('is_active', true)->first();
        $steps = $workflow instanceof ApprovalWorkflow
            ? $workflow->steps->filter(fn ($step): bool => BigDecimal::of($amount)->isGreaterThanOrEqualTo($step->min_amount))->map(fn ($step): array => ['level' => $step->level, 'label' => $step->label, 'permission' => $step->permission])->values()->all()
            : [];

        if (! $workflow instanceof ApprovalWorkflow || $steps === []) {
            return null;
        }

        return DB::transaction(function () use ($workflow, $steps, $documentType, $amount, $reference, $requestedBy, $payload, $propertyId, $subjectType, $subjectId): ApprovalSummary {
            $request = ApprovalRequest::query()->create([
                'property_id' => $propertyId, 'approval_workflow_id' => $workflow->id, 'document_type' => $documentType, 'reference' => mb_substr($reference, 0, 190), 'subject_type' => $subjectType,
                'subject_id' => $subjectId, 'amount' => (string) BigDecimal::of($amount)->toScale(2), 'steps' => $steps, 'current_level' => $steps[0]['level'], 'status' => ApprovalStatus::Pending,
                'payload' => $payload, 'requested_by' => $requestedBy,
            ]);
            $this->record($request, ApprovalActionType::Submitted, $requestedBy, null, null, null);
            $this->tellApprovers($request, $requestedBy);

            return $this->summary($request);
        });
    }

    public function approve(int $requestId, int $userId, ?string $comment = null): ApprovalSummary
    {
        return DB::transaction(function () use ($requestId, $userId, $comment): ApprovalSummary {
            $request = $this->lockedPending($requestId);
            $acting = $this->acting($request, $userId);

            if (! $acting['can']) {
                throw new ApprovalRuleViolated($request->requested_by === $userId ? __('You cannot approve what you asked for yourself.') : __('It is not your decision: this step needs :step.', ['step' => $request->currentStep()['label'] ?? '']));
            }

            $this->record($request, ApprovalActionType::Approved, $userId, $acting['onBehalfOf'], $request->current_level, $comment);
            $next = null;

            foreach ($request->steps as $step) {
                if ($step['level'] > $request->current_level) {
                    $next = $step['level'];
                    break;
                }
            }

            if ($next !== null) {
                $request->forceFill(['current_level' => $next])->save();
                $this->tellApprovers($request, $request->requested_by);

                return $this->summary($request);
            }

            $request->forceFill(['status' => ApprovalStatus::Approved, 'decided_at' => now()])->save();
            $this->registry->handler($request->document_type)->approved($this->decision($request, $userId, $comment));
            $this->tellRequester($request, 'approved', $userId, $comment);

            return $this->summary($request);
        });
    }

    public function reject(int $requestId, int $userId, string $comment): ApprovalSummary
    {
        if (trim($comment) === '') {
            throw new ApprovalRuleViolated(__('Say why you reject it.'));
        }

        return DB::transaction(function () use ($requestId, $userId, $comment): ApprovalSummary {
            $request = $this->lockedPending($requestId);
            $acting = $this->acting($request, $userId);

            if (! $acting['can']) {
                throw new ApprovalRuleViolated(__('It is not your decision: this step needs :step.', ['step' => $request->currentStep()['label'] ?? '']));
            }

            $this->record($request, ApprovalActionType::Rejected, $userId, $acting['onBehalfOf'], $request->current_level, $comment);
            $request->forceFill(['status' => ApprovalStatus::Rejected, 'decided_at' => now()])->save();
            $this->registry->handler($request->document_type)->rejected($this->decision($request, $userId, $comment));
            $this->tellRequester($request, 'rejected', $userId, $comment);

            return $this->summary($request);
        });
    }

    public function cancel(int $requestId, int $userId): ApprovalSummary
    {
        return DB::transaction(function () use ($requestId, $userId): ApprovalSummary {
            $request = $this->lockedPending($requestId);

            if ($request->requested_by !== $userId && ! $this->people->userCan($userId, 'core.approval.manage')) {
                throw new ApprovalRuleViolated(__('Only the person who asked can withdraw the request.'));
            }

            $this->record($request, ApprovalActionType::Cancelled, $userId, null, null, null);
            $request->forceFill(['status' => ApprovalStatus::Cancelled, 'decided_at' => now()])->save();

            return $this->summary($request);
        });
    }

    public function canDecide(int $requestId, int $userId): bool
    {
        $request = ApprovalRequest::query()->find($requestId);

        return $request instanceof ApprovalRequest && $request->status === ApprovalStatus::Pending && $this->acting($request, $userId)['can'];
    }

    public function find(int $requestId): ?ApprovalSummary
    {
        $request = ApprovalRequest::query()->find($requestId);

        return $request instanceof ApprovalRequest ? $this->summary($request) : null;
    }

    public function forSubject(string $documentType, string $subjectType, int $subjectId): array
    {
        return ApprovalRequest::query()->where('document_type', $documentType)->where('subject_type', $subjectType)->where('subject_id', $subjectId)->orderByDesc('id')->get()
            ->map(fn (ApprovalRequest $request): ApprovalSummary => $this->summary($request))->all();
    }

    /**
     * Whether the user may decide the current step, and whose right they use when it is a delegator's.
     *
     * @return array{can: bool, onBehalfOf: int|null}
     */
    public function acting(ApprovalRequest $request, int $userId): array
    {
        $step = $request->currentStep();

        if ($step === null || $request->status !== ApprovalStatus::Pending || $request->requested_by === $userId) {
            return ['can' => false, 'onBehalfOf' => null];
        }

        if ($this->people->userCan($userId, $step['permission'])) {
            return ['can' => true, 'onBehalfOf' => null];
        }

        $today = now()->toDateString();

        foreach (ApprovalDelegation::query()->where('delegate_user_id', $userId)->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->get() as $delegation) {
            if ($delegation->user_id !== $request->requested_by && $this->people->userCan($delegation->user_id, $step['permission'])) {
                return ['can' => true, 'onBehalfOf' => $delegation->user_id];
            }
        }

        return ['can' => false, 'onBehalfOf' => null];
    }

    private function lockedPending(int $requestId): ApprovalRequest
    {
        $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($requestId);

        if ($request->status !== ApprovalStatus::Pending) {
            throw new ApprovalRuleViolated(__('This request was already decided (:status).', ['status' => mb_strtolower($request->status->label())]));
        }

        return $request;
    }

    private function record(ApprovalRequest $request, ApprovalActionType $type, int $userId, ?int $onBehalfOf, ?int $level, ?string $comment): void
    {
        ApprovalAction::query()->create([
            'approval_request_id' => $request->id, 'level' => $level, 'action' => $type->value, 'user_id' => $userId, 'on_behalf_of' => $onBehalfOf,
            'comment' => $comment !== null && trim($comment) !== '' ? mb_substr(trim($comment), 0, 500) : null, 'acted_at' => now(),
        ]);
    }

    private function decision(ApprovalRequest $request, int $decidedBy, ?string $comment): ApprovalDecision
    {
        return new ApprovalDecision($request->id, $request->document_type, $request->reference, (string) $request->amount, $request->payload ?? [], $request->subject_type, $request->subject_id, $request->requested_by, $decidedBy, $comment);
    }

    private function tellApprovers(ApprovalRequest $request, int $requester): void
    {
        $step = $request->currentStep();

        if ($step === null) {
            return;
        }

        $holders = array_values(array_filter($this->people->holders($step['permission']), fn (int $id): bool => $id !== $requester));
        $name = $this->people->names([$requester])[$requester] ?? '';
        $this->people->notify($holders, new ApprovalNotification('requested', $request->reference, number_format((float) $request->amount, 2), $name, '', route('core.approvals.show', $request->id)));
    }

    private function tellRequester(ApprovalRequest $request, string $kind, int $decidedBy, ?string $comment): void
    {
        $name = $this->people->names([$decidedBy])[$decidedBy] ?? '';
        $this->people->notify([$request->requested_by], new ApprovalNotification($kind, $request->reference, number_format((float) $request->amount, 2), $name, (string) $comment, route('core.approvals.show', $request->id)));
    }

    private function summary(ApprovalRequest $request): ApprovalSummary
    {
        return new ApprovalSummary(
            $request->id, $request->document_type, $request->reference, (string) $request->amount, $request->status, $request->current_level, $request->currentStep()['label'] ?? null,
            $request->requested_by, $request->subject_type, $request->subject_id, route('core.approvals.show', $request->id), $request->payload ?? [],
        );
    }
}
