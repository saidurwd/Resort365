<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * A kind of document that goes through approval (refund, purchase order…), registered by the module that owns it
 * with the steps new tenants start from (Step 5.1).
 */
final readonly class ApprovalDocumentType extends Data
{
    /**
     * @param  list<ApprovalStepDefinition>  $steps
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $steps,
    ) {}
}
