<?php

namespace Tests\Fixtures;

use App\Support\DTOs\Data;

final readonly class GuestData extends Data
{
    public function __construct(
        public string $name,
        public ?string $email = null,
    ) {}
}
