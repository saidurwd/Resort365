<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\ApprovalDecision;
use Modules\Core\Exceptions\ApprovalExecutionFailed;

/**
 * What a module does when one of its documents is finally approved or rejected (Step 5.1). `approved` runs in the
 * transaction of the last approval: throw `ApprovalExecutionFailed` when the document can no longer go ahead, and the
 * approval is not recorded.
 */
interface ApprovalHandler
{
    /**
     * @throws ApprovalExecutionFailed
     */
    public function approved(ApprovalDecision $decision): void;

    public function rejected(ApprovalDecision $decision): void;
}
