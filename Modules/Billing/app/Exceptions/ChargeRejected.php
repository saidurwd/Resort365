<?php

namespace Modules\Billing\Exceptions;

use RuntimeException;

/**
 * A folio posting was refused; the message says why (not checked in, folio closed, over the
 * credit limit, unknown charge code…). Nothing was posted.
 */
class ChargeRejected extends RuntimeException {}
