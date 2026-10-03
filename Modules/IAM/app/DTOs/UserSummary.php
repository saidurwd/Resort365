<?php

namespace Modules\IAM\DTOs;

use App\Support\DTOs\Data;

/**
 * A user as other modules see them (no IAM model leaves the module).
 */
final readonly class UserSummary extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $status,
    ) {}
}
