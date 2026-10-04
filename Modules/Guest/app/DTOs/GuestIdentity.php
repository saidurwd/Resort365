<?php

namespace Modules\Guest\DTOs;

use App\Support\DTOs\Data;
use Modules\Guest\Enums\IdType;

/**
 * A guest's identity document as captured at check-in (GuestRegistry::recordIdentity).
 * idExpiry is Y-m-d; nationalityCode is ISO 3166 alpha-2.
 */
final readonly class GuestIdentity extends Data
{
    public function __construct(
        public int $guestId,
        public IdType $idType,
        public string $idNumber,
        public ?string $idExpiry = null,
        public ?string $nationalityCode = null,
    ) {}
}
