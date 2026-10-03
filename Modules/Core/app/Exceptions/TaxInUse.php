<?php

namespace Modules\Core\Exceptions;

use RuntimeException;

/**
 * A tax is still part of a tax category; the message is shown to the user.
 */
class TaxInUse extends RuntimeException {}
