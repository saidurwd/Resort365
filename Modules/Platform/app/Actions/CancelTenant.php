<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Closes a tenant (Step 7.4): its subdomain stops answering and its subscription ends. Its data stays for
 * `platform.billing.retention_days`, during which an admin can still export it from the console; `platform:purge-tenants` deletes it after that.
 */
class CancelTenant extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, string $reason, ?PlatformAdmin $admin = null): Tenant
    {
        if ($tenant->status === TenantStatus::Cancelled) {
            throw new PlatformRuleViolated(__(':tenant is already cancelled.', ['tenant' => $tenant->name]));
        }

        return $this->transaction(function () use ($tenant, $reason, $admin): Tenant {
            Subscription::query()->where('tenant_id', $tenant->id)->where('status', '!=', SubscriptionStatus::Cancelled->value)
                ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => now(), 'updated_at' => now()]);
            $tenant->forceFill(['status' => TenantStatus::Cancelled, 'cancelled_at' => now()])->save();
            $this->audit->log($admin, 'tenant.cancelled', __(':tenant cancelled', ['tenant' => $tenant->name]), $tenant->id, ['reason' => $reason]);

            return $tenant;
        });
    }
}
