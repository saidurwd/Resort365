<?php

namespace Modules\Guest\Contracts;

use Illuminate\Http\UploadedFile;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestIdentity;
use Modules\Guest\DTOs\GuestSummary;

/**
 * Registers new guests for other modules (the booking wizard), the same way the guest form does
 * (normalised phone, lower-case email). Check GuestLookup::findDuplicates() first.
 */
interface GuestRegistry
{
    public function register(GuestDetails $details): GuestSummary;

    /**
     * Records a guest's ID document (check-in): type, number (encrypted, hashed for duplicate
     * search), expiry and nationality, and optionally a scan added to the guest's ID documents.
     */
    public function recordIdentity(GuestIdentity $identity, ?UploadedFile $scan = null, ?int $uploaderId = null, ?string $uploaderName = null): GuestSummary;
}
