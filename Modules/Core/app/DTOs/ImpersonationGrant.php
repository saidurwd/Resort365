<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * What a platform admin's one-time sign-in link grants (Step 7.3): to be signed in as one tenant user, for a logged session.
 */
final readonly class ImpersonationGrant extends Data
{
    public function __construct(
        public int $sessionId,
        public int $userId,
        public int $adminId,
        public string $adminName,
    ) {}
}
