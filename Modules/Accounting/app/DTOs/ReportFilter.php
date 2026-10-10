<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;

/**
 * What a financial report covers (Step 4.5): the dates, a property (`'none'` = lines without one, null = all) and,
 * for the general ledger, a department and party.
 */
final readonly class ReportFilter extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $property = null,
        public ?int $departmentId = null,
        public ?string $partyType = null,
        public ?int $partyId = null,
    ) {}

    public function propertyId(): ?int
    {
        return $this->property !== null && ctype_digit($this->property) ? (int) $this->property : null;
    }

    public function withoutProperty(): bool
    {
        return $this->property === 'none';
    }
}
