<?php

namespace Modules\Restaurant\Exceptions;

use RuntimeException;

/**
 * A restaurant setup change that is not allowed (a station without its printer, an area with tables…).
 */
class RestaurantSetupInvalid extends RuntimeException {}
