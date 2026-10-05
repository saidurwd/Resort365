<?php

namespace Modules\IAM\Exceptions;

use RuntimeException;

/**
 * A POS PIN that cannot be used (wrong length, or another person's).
 */
class PosPinNotAllowed extends RuntimeException {}
