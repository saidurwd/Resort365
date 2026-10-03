<?php

namespace Modules\Guest\Contracts;

use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestSummary;

/**
 * Registers new guests for other modules (the booking wizard), the same way the guest form does
 * (normalised phone, lower-case email). Check GuestLookup::findDuplicates() first.
 */
interface GuestRegistry
{
    public function register(GuestDetails $details): GuestSummary;
}
