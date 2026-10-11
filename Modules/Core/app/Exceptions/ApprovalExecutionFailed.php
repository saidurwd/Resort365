<?php

namespace Modules\Core\Exceptions;

use DomainException;

/**
 * A module's handler cannot carry out an approved document any more (the refundable amount changed…). The approval is
 * not recorded; the approver may reject the request instead.
 */
class ApprovalExecutionFailed extends DomainException {}
