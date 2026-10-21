<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * A message from the platform to the tenants (Step 7.3), as the page shows it. `level` is info, warning or danger.
 */
final readonly class AnnouncementSummary extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $body,
        public string $level,
    ) {}
}
