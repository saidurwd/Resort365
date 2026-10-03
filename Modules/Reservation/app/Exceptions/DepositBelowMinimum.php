<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * The deposit percent is outside the deposit policy's limits and the user may not override them.
 */
class DepositBelowMinimum extends RuntimeException {}
