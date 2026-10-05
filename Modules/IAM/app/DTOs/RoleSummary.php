<?php

namespace Modules\IAM\DTOs;

use App\Support\DTOs\Data;

/**
 * A tenant's role as other modules see it: id, display name (translated for system roles), and the
 * default role it is, if any.
 */
final readonly class RoleSummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $defaultRole,
    ) {}
}
