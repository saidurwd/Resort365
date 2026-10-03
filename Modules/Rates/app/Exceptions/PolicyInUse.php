<?php

namespace Modules\Rates\Exceptions;

use RuntimeException;

/**
 * A deposit or cancellation policy is still used by rate plans; the message is shown to the user.
 */
class PolicyInUse extends RuntimeException {}
