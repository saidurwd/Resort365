<?php

namespace Modules\HR\Exceptions;

use DomainException;

/**
 * An HR rule that is not allowed (a manager who reports to the employee, a duplicate ID, a change to an employee who
 * has left…). The message is meant for the person.
 */
class HrRuleViolated extends DomainException {}
