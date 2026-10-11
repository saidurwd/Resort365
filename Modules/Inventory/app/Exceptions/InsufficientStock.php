<?php

namespace Modules\Inventory\Exceptions;

/**
 * More was asked of a store than it holds, and the tenant does not allow negative stock.
 */
class InsufficientStock extends StockRuleViolated {}
