<?php

namespace Modules\Inventory\Exceptions;

use DomainException;

/**
 * A quantity cannot be turned into the item's base unit: the unit is of another kind and the item has no conversion for it.
 */
class UnitNotConvertible extends DomainException {}
