<?php

namespace Modules\Procurement\Exceptions;

use DomainException;

/**
 * A procurement rule that is not allowed (an order in the wrong state, a vendor with no email, a quotation for an item
 * the RFQ does not ask for…). The message is meant for the person.
 */
class ProcurementRuleViolated extends DomainException {}
