<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * A default step of a document type's workflow (Step 5.1): approvers hold `permission`; the step applies to
 * amounts from `minAmount`.
 */
final readonly class ApprovalStepDefinition extends Data
{
    public function __construct(
        public string $label,
        public string $permission,
        public string $minAmount = '0.00',
    ) {}
}
