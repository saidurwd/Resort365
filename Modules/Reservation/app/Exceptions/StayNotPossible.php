<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * A front-desk operation on a stay is not possible now (e.g. checking in a tentative booking, or
 * moving to a room that is taken); the message says why.
 */
class StayNotPossible extends RuntimeException {}
