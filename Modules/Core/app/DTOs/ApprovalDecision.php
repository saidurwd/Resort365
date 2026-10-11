<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * What a module's approval handler receives when a request is finally approved or rejected (Step 5.1).
 */
final readonly class ApprovalDecision extends Data
{
    /**
     * @param  array<string, mixed>  $payload  what the module stored when it submitted the request
     */
    public function __construct(
        public int $requestId,
        public string $documentType,
        public string $reference,
        public string $amount,
        public array $payload,
        public ?string $subjectType,
        public ?int $subjectId,
        public int $requestedBy,
        public int $decidedBy,
        public ?string $comment,
    ) {}
}
