<?php

namespace Modules\Rates\Exceptions;

use RuntimeException;

/**
 * A rate plan cannot be deleted while bookings use it.
 */
class RatePlanInUse extends RuntimeException {}
