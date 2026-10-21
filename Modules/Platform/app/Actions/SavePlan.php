<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\ModuleCatalog;
use Modules\Platform\Services\PlatformAudit;

/**
 * Creates or changes a plan (Step 7.3). The modules always include what they need; a limit left empty is no limit; the code is
 * unique. Changing a plan does not touch the tenants on it until they are moved or their modules are applied again.
 */
class SavePlan extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @param  array{code: string, name: string, description?: string|null, monthly_price: string|int|float, yearly_price: string|int|float, currency_code: string, max_properties?: int|null, max_rooms?: int|null, max_users?: int|null, storage_gb?: int|null, modules?: list<string>, api_access?: bool, is_active?: bool, sort_order?: int}  $data
     *
     * @throws PlatformRuleViolated
     */
    public function handle(?Plan $plan, array $data, ?PlatformAdmin $admin = null): Plan
    {
        $code = strtolower(trim($data['code']));

        if (preg_match('/^[a-z0-9][a-z0-9-]{1,29}$/', $code) !== 1) {
            throw new PlatformRuleViolated(__('The code is 2 to 30 lowercase letters, digits and hyphens.'));
        }

        if (Plan::query()->where('code', $code)->when($plan instanceof Plan, fn ($query) => $query->whereKeyNot($plan?->id))->exists()) {
            throw new PlatformRuleViolated(__('There is a plan with the code ":code" already.', ['code' => $code]));
        }

        foreach (['max_properties', 'max_rooms', 'max_users', 'storage_gb'] as $limit) {
            if (isset($data[$limit]) && (int) $data[$limit] < 1) {
                throw new PlatformRuleViolated(__('A limit is at least 1; leave it empty for no limit.'));
            }
        }

        if ((float) $data['monthly_price'] < 0 || (float) $data['yearly_price'] < 0) {
            throw new PlatformRuleViolated(__('A price is not negative.'));
        }

        $fields = [
            'code' => $code, 'name' => trim($data['name']), 'description' => $data['description'] ?? null, 'monthly_price' => $data['monthly_price'], 'yearly_price' => $data['yearly_price'], 'currency_code' => strtoupper($data['currency_code']),
            'max_properties' => $data['max_properties'] ?? null, 'max_rooms' => $data['max_rooms'] ?? null, 'max_users' => $data['max_users'] ?? null, 'storage_gb' => $data['storage_gb'] ?? null,
            'modules' => ModuleCatalog::withDependencies($data['modules'] ?? []), 'api_access' => (bool) ($data['api_access'] ?? false), 'is_active' => (bool) ($data['is_active'] ?? true), 'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        return $this->transaction(function () use ($plan, $fields, $admin): Plan {
            $created = ! $plan instanceof Plan;
            $plan ??= new Plan;
            $plan->fill($fields)->save();
            $this->audit->log($admin, $created ? 'plan.created' : 'plan.updated', ($created ? __('Plan :name made', ['name' => $plan->name]) : __('Plan :name changed', ['name' => $plan->name])), null, ['plan_id' => $plan->id]);

            return $plan;
        });
    }
}
