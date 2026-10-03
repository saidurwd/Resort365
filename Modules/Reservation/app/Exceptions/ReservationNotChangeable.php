<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * The reservation's status does not allow this change (e.g. modifying a cancelled booking).
 */
class ReservationNotChangeable extends RuntimeException {}
