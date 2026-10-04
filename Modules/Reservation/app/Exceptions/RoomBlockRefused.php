<?php

namespace Modules\Reservation\Exceptions;

use RuntimeException;

/**
 * A room cannot be blocked (out of order) for those nights: a booking or another block holds it.
 */
class RoomBlockRefused extends RuntimeException {}
