<?php

namespace Modules\Guest\DTOs;

use App\Support\DTOs\Data;

/**
 * A guest as other modules see it (GuestLookup). No ID number; idType (and idExpiry, Y-m-d) say
 * whether an ID document is on file.
 */
final readonly class GuestSummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public ?string $nationalityCode,
        public ?int $companyId,
        public string $vipLevel,
        public bool $isBlacklisted,
        public ?string $blacklistReason,
        public ?string $idType = null,
        public ?string $idExpiry = null,
    ) {}
}
