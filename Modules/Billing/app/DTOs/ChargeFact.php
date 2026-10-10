<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * A folio charge or adjustment as the ledger needs it (Accounting, Step 4.2): net amount, taxes by name
 * and the meal part of a room night (package split). Amounts are decimal strings; adjustments may be
 * negative.
 */
final readonly class ChargeFact extends Data
{
    /**
     * @param  array<string, string>  $taxLines  tax name => amount
     */
    public function __construct(
        public int $lineId,
        public int $propertyId,
        public int $folioId,
        public ?int $reservationId,
        public string $postingDate,
        public ?string $chargeCode,
        public string $category,
        public string $amount,
        public string $taxAmount,
        public array $taxLines,
        public string $mealAmount,
        public bool $isVoided,
    ) {}
}
