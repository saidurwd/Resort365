<?php

namespace Modules\IAM\Policies;

use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * API tokens (Step 8.1): `iam.api-token.create` makes and revokes your own; `iam.api-token.manage` sees and revokes everyone's.
 */
class ApiTokenPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('iam.api-token.create') || $actor->checkPermissionTo('iam.api-token.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->checkPermissionTo('iam.api-token.create');
    }

    public function delete(User $actor, ApiToken $token): bool
    {
        return $actor->checkPermissionTo('iam.api-token.manage')
            || ($token->tokenable_type === $actor->getMorphClass() && $token->tokenable_id === $actor->id && $actor->checkPermissionTo('iam.api-token.create'));
    }
}
