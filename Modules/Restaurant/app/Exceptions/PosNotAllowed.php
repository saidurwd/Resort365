<?php

namespace Modules\Restaurant\Exceptions;

use RuntimeException;

/**
 * A POS action that is not allowed (wrong PIN, a session already open, an approval that does not fit…).
 */
class PosNotAllowed extends RuntimeException {}
