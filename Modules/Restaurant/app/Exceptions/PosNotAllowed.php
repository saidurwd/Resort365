<?php

namespace Modules\Restaurant\Exceptions;

use RuntimeException;

/**
 * A POS action that is not allowed (wrong PIN, a session already open, an approval that does not fit…).
 * When a manager's PIN would allow it, `approval` names the ManagerApprovals action to ask for.
 */
class PosNotAllowed extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $approval = null)
    {
        parent::__construct($message);
    }

    public static function needsApproval(string $message, string $action): self
    {
        return new self($message, $action);
    }
}
