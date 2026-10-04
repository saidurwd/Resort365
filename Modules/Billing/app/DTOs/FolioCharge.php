<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A charge to post to a reservation's folio (FolioPostingContract, the Folios tab). unitPrice is a
 * decimal string, net of tax unless priceIncludesTax. folioId null = the folio the reservation's
 * routing rules choose for the charge code's category (else the guest folio).
 * revenuePostedBySource: the source module already recognised the revenue (ARCHITECTURE §7), so
 * Billing only moves the receivable.
 */
final readonly class FolioCharge extends Data
{
    public function __construct(
        public int $reservationId,
        public int $chargeCodeId,
        public string $unitPrice,
        public int $quantity = 1,
        public ?string $description = null,
        public bool $priceIncludesTax = false,
        public ?int $folioId = null,
        public ?int $extraServiceId = null,
        public ?string $referenceType = null,
        public ?int $referenceId = null,
        public bool $revenuePostedBySource = false,
        public ?int $postedBy = null,
    ) {}
}
