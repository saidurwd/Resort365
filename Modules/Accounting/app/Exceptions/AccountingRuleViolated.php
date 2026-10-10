<?php

namespace Modules\Accounting\Exceptions;

use RuntimeException;

/**
 * An accounting rule was broken (ARCHITECTURE §7.2): an unbalanced entry, a closed period, a posted entry
 * changed, an account that cannot be used. The message says which, for the person doing it.
 */
class AccountingRuleViolated extends RuntimeException {}
