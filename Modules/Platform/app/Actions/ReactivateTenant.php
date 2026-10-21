<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Lets a suspended tenant work again (Step 7.3): back to trial if its trial has not ended, else active.
 */
class ReactivateTenant extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, ?PlatformAdmin $admin = null): Tenant
    {
        if ($tenant->status !== TenantStatus::Suspended) {
            throw new PlatformRuleViolated(__(':tenant is not suspended.', ['tenant' => $tenant->name]));
        }

        return $this->transaction(function () use ($tenant, $admin): Tenant {
            $tenant->forceFill(['status' => $tenant->trial_ends_at?->isFuture() ? TenantStatus::Trial : TenantStatus::Active, 'suspended_at' => null, 'suspended_for' => null])->save();
            $this->audit->log($admin, 'tenant.reactivated', __(':tenant reactivated', ['tenant' => $tenant->name]), $tenant->id);

            return $tenant;
        });
    }
}
