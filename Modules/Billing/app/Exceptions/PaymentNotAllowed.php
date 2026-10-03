<?php

namespace Modules\Billing\Exceptions;

use RuntimeException;

/**
 * A payment cannot be taken (unknown or cancelled reservation, or more than the balance due).
 */
class PaymentNotAllowed extends RuntimeException {}
