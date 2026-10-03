<?php

namespace Modules\Guest\DTOs;

use App\Support\DTOs\Data;

/**
 * A travel agent as other modules see it (GuestLookup). Percent and amount are decimal strings.
 */
final readonly class TravelAgentSummary extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $commissionPercent,
        public string $creditLimit,
        public bool $isActive,
    ) {}
}
