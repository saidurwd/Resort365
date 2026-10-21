<?php

namespace Modules\Platform\Actions;

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Support\Actions\Action;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\TenantContext;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\ModuleCatalog;
use Modules\Platform\Services\PlatformAudit;
use Modules\Platform\Services\TenantPlanLimits;
use Modules\Platform\Services\UsageCollector;

/**
 * Moves a tenant to a plan, or to no plan (Step 7.3). The plan's modules are switched on for the tenant and all the others off;
 * their data stays, and their menus and pages go. A tenant that already uses more than the new plan allows keeps what it has but
 * cannot add more: the answer lists those. No plan switches every module on and removes the limits.
 */
class ChangeTenantPlan extends Action
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ModuleAccess $modules,
        private readonly TenantPlanLimits $limits,
        private readonly UsageCollector $usage,
        private readonly PlatformAudit $audit,
    ) {}

    /**
     * @return list<string> what is over the new plan's limits
     *
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, ?Plan $plan, ?PlatformAdmin $admin = null): array
    {
        if ($plan instanceof Plan && ! $plan->is_active && $tenant->plan_id !== $plan->id) {
            throw new PlatformRuleViolated(__('The :name plan is inactive, so tenants cannot be moved to it.', ['name' => $plan->name]));
        }

        $before = $tenant->plan_id;

        $this->transaction(function () use ($tenant, $plan, $admin, $before): void {
            $tenant->forceFill(['plan_id' => $plan?->id])->save();
            $this->context->run($tenant, function () use ($plan): void {
                foreach (ModuleCatalog::aliases() as $module) {
                    TenantModule::query()->updateOrCreate(['module' => $module], ['enabled' => ! $plan instanceof Plan || in_array($module, $plan->modules, true)]);
                }
            });
            $this->audit->log($admin, 'tenant.plan_changed', $plan instanceof Plan ? __(':tenant moved to the :plan plan', ['tenant' => $tenant->name, 'plan' => $plan->name]) : __(':tenant taken off its plan', ['tenant' => $tenant->name]), $tenant->id, ['from' => $before, 'to' => $plan?->id]);
        });

        $this->modules->flush();
        $this->limits->flush();

        return $plan instanceof Plan ? $this->usage->overLimits($tenant, $plan) : [];
    }
}
