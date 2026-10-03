<?php

namespace Modules\Guest\DTOs;

use App\Support\DTOs\Data;

/**
 * The basic details of a new guest, as another module (the booking wizard) registers them.
 */
final readonly class GuestDetails extends Data
{
    public function __construct(
        public string $firstName,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $title = null,
        public ?string $nationalityCode = null,
        public ?int $companyId = null,
    ) {}
}
