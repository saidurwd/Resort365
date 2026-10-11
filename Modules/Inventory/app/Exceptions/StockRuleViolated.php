<?php

namespace Modules\Inventory\Exceptions;

use DomainException;

/**
 * A stock rule that is not allowed (a movement changed, an item that is not stocked, a transfer in the wrong state…).
 */
class StockRuleViolated extends DomainException {}
