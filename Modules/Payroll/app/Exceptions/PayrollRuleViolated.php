<?php

namespace Modules\Payroll\Exceptions;

use DomainException;

/**
 * A payroll rule that is not allowed (a formula that cannot be worked out, a circular structure, a salary that starts before
 * the one it replaces…). The message is meant for the person.
 */
class PayrollRuleViolated extends DomainException {}
