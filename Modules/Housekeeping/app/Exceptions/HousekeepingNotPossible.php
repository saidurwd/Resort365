<?php

namespace Modules\Housekeeping\Exceptions;

use RuntimeException;

/**
 * A housekeeping or maintenance change that is not allowed (wrong step, room booked, …).
 */
class HousekeepingNotPossible extends RuntimeException {}
