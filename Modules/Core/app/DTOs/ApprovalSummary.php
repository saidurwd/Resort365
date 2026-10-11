<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Modules\Core\Enums\ApprovalStatus;

/**
 * An approval request as other modules see it (Step 5.1).
 */
final readonly class ApprovalSummary extends Data
{
    /**
     * @param  array<string, mixed>  $payload  what the module stored when it submitted the request
     */
    public function __construct(
        public int $id,
        public string $documentType,
        public string $reference,
        public string $amount,
        public ApprovalStatus $status,
        public int $currentLevel,
        public ?string $currentStep,
        public int $requestedBy,
        public ?string $subjectType,
        public ?int $subjectId,
        public string $url,
        public array $payload = [],
    ) {}
}
