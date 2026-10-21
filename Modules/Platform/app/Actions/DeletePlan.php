<?php

namespace Modules\Platform\Actions;

use App\Models\Tenant;
use App\Support\Actions\Action;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Deletes a plan no tenant is on (Step 7.3); one in use can be made inactive instead, so no new tenant picks it.
 */
class DeletePlan extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Plan $plan, ?PlatformAdmin $admin = null): void
    {
        $tenants = Tenant::query()->where('plan_id', $plan->id)->count();

        if ($tenants > 0) {
            throw new PlatformRuleViolated(trans_choice(':count tenant is on :name. Move it to another plan first, or make the plan inactive.|:count tenants are on :name. Move them to another plan first, or make the plan inactive.', $tenants, ['count' => $tenants, 'name' => $plan->name]));
        }

        $this->transaction(function () use ($plan, $admin): void {
            $this->audit->log($admin, 'plan.deleted', __('Plan :name deleted', ['name' => $plan->name]), null, ['plan_id' => $plan->id]);
            $plan->delete();
        });
    }
}
