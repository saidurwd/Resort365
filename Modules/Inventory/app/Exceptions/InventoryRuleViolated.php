<?php

namespace Modules\Inventory\Exceptions;

use DomainException;

/**
 * A catalogue rule that is not allowed (a unit of the wrong kind, a category inside itself, a bad import line…).
 */
class InventoryRuleViolated extends DomainException {}
