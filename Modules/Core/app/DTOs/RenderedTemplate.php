<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

final readonly class RenderedTemplate extends Data
{
    public function __construct(
        public ?string $subject,
        public string $body,
    ) {}
}
