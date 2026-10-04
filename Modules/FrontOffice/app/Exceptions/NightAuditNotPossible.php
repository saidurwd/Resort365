<?php

namespace Modules\FrontOffice\Exceptions;

use RuntimeException;

/**
 * The night audit cannot run now (already done for the date, running, or the date is ahead of the
 * calendar).
 */
class NightAuditNotPossible extends RuntimeException {}
