<?php

namespace Modules\Billing\Exceptions;

use RuntimeException;

/**
 * A payment gateway could not be reached or refused the request (Step 8.3). The message is for staff and the log; guests see a
 * plain "the payment could not be started".
 */
class GatewayError extends RuntimeException {}
