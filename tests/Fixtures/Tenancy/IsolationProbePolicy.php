<?php

namespace Tests\Fixtures\Tenancy;

use Modules\IAM\Models\User;

/**
 * Test policy: any signed-in user may view a probe's files; changing them needs iam.user.update.
 */
class IsolationProbePolicy
{
    public function view(User $user, IsolationProbe $probe): bool
    {
        return true;
    }

    public function update(User $user, IsolationProbe $probe): bool
    {
        return $user->checkPermissionTo('iam.user.update');
    }
}
