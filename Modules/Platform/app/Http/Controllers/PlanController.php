<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Actions\DeletePlan;
use Modules\Platform\Actions\SavePlan;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Http\Requests\PlanRequest;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\ModuleCatalog;

/**
 * Platform console → Plans (Step 7.3): prices, limits and the modules each plan includes.
 */
class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('platform::plans.index', ['plans' => $plans, 'tenants' => Tenant::query()->selectRaw('plan_id, COUNT(*) as n')->groupBy('plan_id')->pluck('n', 'plan_id'), 'modules' => ModuleCatalog::labels()]);
    }

    public function create(): View
    {
        return view('platform::plans.form', ['plan' => new Plan(['currency_code' => 'USD', 'modules' => ModuleCatalog::aliases(), 'is_active' => true, 'monthly_price' => '0', 'yearly_price' => '0']), 'catalog' => ModuleCatalog::MODULES]);
    }

    public function store(PlanRequest $request, SavePlan $save): RedirectResponse
    {
        try {
            $plan = $save->handle(null, $this->data($request), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.plans.index')->with('success', __('The :name plan is made.', ['name' => $plan->name]));
    }

    public function edit(Plan $plan): View
    {
        return view('platform::plans.form', ['plan' => $plan, 'catalog' => ModuleCatalog::MODULES]);
    }

    public function update(PlanRequest $request, Plan $plan, SavePlan $save): RedirectResponse
    {
        try {
            $save->handle($plan, $this->data($request), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.plans.index')->with('success', __('The :name plan is saved. Tenants on it keep their modules until you move them or apply the plan again.', ['name' => $plan->name]));
    }

    public function destroy(Request $request, Plan $plan, DeletePlan $delete): RedirectResponse
    {
        try {
            $delete->handle($plan, $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('platform.plans.index')->with('success', __('The plan is deleted.'));
    }

    /**
     * @return array{code: string, name: string, description: string|null, monthly_price: string, yearly_price: string, currency_code: string, max_properties: int|null, max_rooms: int|null, max_users: int|null, storage_gb: int|null, modules: list<string>, api_access: bool, is_active: bool, sort_order: int}
     */
    private function data(PlanRequest $request): array
    {
        $v = $request->validated();
        $limit = fn (string $key): ?int => ($v[$key] ?? null) !== null && $v[$key] !== '' ? (int) $v[$key] : null;

        return [
            'code' => (string) $v['code'], 'name' => (string) $v['name'], 'description' => $v['description'] ?? null, 'monthly_price' => (string) $v['monthly_price'], 'yearly_price' => (string) $v['yearly_price'], 'currency_code' => (string) $v['currency_code'],
            'max_properties' => $limit('max_properties'), 'max_rooms' => $limit('max_rooms'), 'max_users' => $limit('max_users'), 'storage_gb' => $limit('storage_gb'),
            'modules' => array_values($v['modules'] ?? []), 'api_access' => $request->boolean('api_access'), 'is_active' => $request->boolean('is_active'), 'sort_order' => (int) ($v['sort_order'] ?? 0),
        ];
    }
}
