<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\ApprovalSummary;
use Modules\Core\Exceptions\ApprovalExecutionFailed;
use Modules\Core\Exceptions\ApprovalRuleViolated;

/**
 * The approval workflow engine (ARCHITECTURE §5.1, Step 5.1). A module submits a document with its amount; if the
 * tenant's workflow needs no approval for it, nothing is created and the module goes ahead at once. Otherwise
 * a request waits for the approvers of each level that applies, and the module's handler runs on final approval.
 */
interface ApprovalEngine
{
    /**
     * @param  array<string, mixed>|null  $payload  stored with the request and handed to the handler
     * @return ApprovalSummary|null null when no approval is needed
     */
    public function submit(string $documentType, string $amount, string $reference, int $requestedBy, ?array $payload = null, ?int $propertyId = null, ?string $subjectType = null, ?int $subjectId = null): ?ApprovalSummary;

    /**
     * @throws ApprovalRuleViolated
     * @throws ApprovalExecutionFailed
     */
    public function approve(int $requestId, int $userId, ?string $comment = null): ApprovalSummary;

    /**
     * @throws ApprovalRuleViolated
     */
    public function reject(int $requestId, int $userId, string $comment): ApprovalSummary;

    /**
     * @throws ApprovalRuleViolated
     */
    public function cancel(int $requestId, int $userId): ApprovalSummary;

    /**
     * Whether the user may decide the request's current step now (holds its permission or is an active delegate of someone who does, and did not ask for it).
     */
    public function canDecide(int $requestId, int $userId): bool;

    public function find(int $requestId): ?ApprovalSummary;

    /**
     * Requests of a document type about one subject, newest first.
     *
     * @return list<ApprovalSummary>
     */
    public function forSubject(string $documentType, string $subjectType, int $subjectId): array;
}
