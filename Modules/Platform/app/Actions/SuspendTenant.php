<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Suspends a tenant (Step 7.3): its people see the suspended page and cannot work until it is reactivated. The reason is kept in the
 * console's audit log. A suspension for an overdue platform invoice (`unpaid`, Step 7.4) is lifted by paying it.
 */
class SuspendTenant extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @param  'admin'|'unpaid'  $for
     *
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, string $reason, ?PlatformAdmin $admin = null, string $for = 'admin'): Tenant
    {
        if (trim($reason) === '') {
            throw new PlatformRuleViolated(__('Say why the tenant is suspended.'));
        }

        if ($tenant->status !== TenantStatus::Active && $tenant->status !== TenantStatus::Trial) {
            throw new PlatformRuleViolated(__(':tenant is :status, so it cannot be suspended.', ['tenant' => $tenant->name, 'status' => mb_strtolower($tenant->status->label())]));
        }

        return $this->transaction(function () use ($tenant, $reason, $admin, $for): Tenant {
            $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_at' => now(), 'suspended_for' => $for])->save();
            $this->audit->log($admin, 'tenant.suspended', __(':tenant suspended', ['tenant' => $tenant->name]), $tenant->id, ['reason' => trim($reason), 'for' => $for]);

            return $tenant;
        });
    }
}
