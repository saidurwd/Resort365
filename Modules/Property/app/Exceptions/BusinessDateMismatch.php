<?php

namespace Modules\Property\Exceptions;

use RuntimeException;

/**
 * The business date is not the one the caller expected (the night audit of that date already ran).
 */
class BusinessDateMismatch extends RuntimeException {}
