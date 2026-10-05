<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;

/**
 * Where a room-charge or city-ledger payment goes: an in-house booking (with the guest's signature as a
 * PNG data URL, if signed on screen) or a company's account.
 */
final readonly class ChargeTarget extends Data
{
    public function __construct(
        public ?int $reservationId = null,
        public ?int $companyId = null,
        public ?string $signature = null,
    ) {}
}
