<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * The booking cannot be made as asked (no rate, unknown or doubled unit…); the message is shown.
 */
class BookingNotPossible extends RuntimeException {}
