<?php

namespace Modules\Core\Exceptions;

use DomainException;

/**
 * An approval step that is not allowed (not your turn, already decided, a missing comment…). The message is for the person.
 */
class ApprovalRuleViolated extends DomainException {}
