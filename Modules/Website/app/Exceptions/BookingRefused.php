<?php

namespace Modules\Website\Exceptions;

use RuntimeException;

/**
 * The website will not take this booking (Step 8.2b): too many open bookings from the same email or address, or a guest the resort
 * does not accept online. The message is polite and says to contact the resort; it never says why.
 */
class BookingRefused extends RuntimeException {}
