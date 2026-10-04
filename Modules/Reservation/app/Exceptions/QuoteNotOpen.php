<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * The quote is booked, declined or past its validity date, so it cannot be sent or booked.
 */
class QuoteNotOpen extends RuntimeException {}
