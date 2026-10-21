<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\ReactivateTenant;
use Modules\Platform\Actions\StartImpersonation;
use Modules\Platform\Actions\SuspendTenant;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Http\Requests\ChangePlanRequest;
use Modules\Platform\Http\Requests\ReasonRequest;
use Modules\Platform\Models\ImpersonationSession;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAuditEntry;
use Modules\Platform\Services\UsageCollector;

/**
 * Platform console → Tenants (Step 7.3): the list with plan and usage, and a tenant's page with its usage against its plan, the
 * plan change, suspend and reactivate, and signing in as one of its users.
 */
class TenantController extends Controller
{
    public function index(Request $request, UsageCollector $usage): View
    {
        $search = trim((string) $request->query('q', ''));
        $tenants = Tenant::query()->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', $search.'%')))->orderBy('name')->limit(200)->get();
        $plans = Plan::query()->get()->keyBy('id');

        return view('platform::tenants.index', [
            'tenants' => $tenants, 'plans' => $plans, 'search' => $search,
            'usage' => $tenants->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $usage->forTenant($tenant, $plans->get($tenant->plan_id))]),
        ]);
    }

    public function show(Tenant $tenant, UsageCollector $usage, TenantContext $context, UserDirectory $users): View
    {
        $plan = $tenant->plan_id !== null ? Plan::query()->find($tenant->plan_id) : null;

        return view('platform::tenants.show', [
            'tenant' => $tenant, 'plan' => $plan, 'usage' => $usage->forTenant($tenant, $plan), 'plans' => Plan::query()->orderBy('sort_order')->orderBy('name')->get(),
            'people' => $tenant->status->canAccess() ? $context->run($tenant, fn (): array => collect($users->all())->filter(fn (UserSummary $user): bool => $user->status === 'active')->values()->all()) : [],
            'audit' => PlatformAuditEntry::query()->where('tenant_id', $tenant->id)->latest('id')->limit(15)->get(),
            'sessions' => ImpersonationSession::query()->with('admin')->where('tenant_id', $tenant->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function changePlan(ChangePlanRequest $request, Tenant $tenant, ChangeTenantPlan $change): RedirectResponse
    {
        $plan = $request->filled('plan_id') ? Plan::query()->findOrFail((int) $request->validated('plan_id')) : null;

        try {
            $over = $change->handle($tenant, $plan, $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $redirect = to_route('platform.tenants.show', $tenant)->with('success', $plan instanceof Plan ? __(':tenant is on the :plan plan.', ['tenant' => $tenant->name, 'plan' => $plan->name]) : __(':tenant has no plan: every module, no limits.', ['tenant' => $tenant->name]));

        return $over === [] ? $redirect : $redirect->with('warning', implode(' ', $over));
    }

    public function suspend(ReasonRequest $request, Tenant $tenant, SuspendTenant $suspend): RedirectResponse
    {
        try {
            $suspend->handle($tenant, (string) $request->validated('reason'), $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.tenants.show', $tenant)->with('success', __(':tenant is suspended.', ['tenant' => $tenant->name]));
    }

    public function reactivate(Request $request, Tenant $tenant, ReactivateTenant $reactivate): RedirectResponse
    {
        try {
            $reactivate->handle($tenant, $request->user('platform'));
        } catch (PlatformRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('platform.tenants.show', $tenant)->with('success', __(':tenant is active again.', ['tenant' => $tenant->name]));
    }

    public function impersonate(ReasonRequest $request, Tenant $tenant, StartImpersonation $start): RedirectResponse
    {
        $request->validateResolved();

        try {
            $url = $start->handle($request->user('platform'), $tenant, (int) $request->input('user_id'), (string) $request->validated('reason'));
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->away($url);
    }
}
