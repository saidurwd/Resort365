<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Platform\Actions\CancelSubscription;
use Modules\Platform\Actions\CancelTenant;
use Modules\Platform\Actions\ChangeSubscription;
use Modules\Platform\Actions\ResumeSubscription;
use Modules\Platform\Actions\StartSubscription;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Http\Requests\ReasonRequest;
use Modules\Platform\Http\Requests\SubscriptionRequest;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\Subscription;

/**
 * Platform console → a tenant's subscription (Step 7.4): start the paid subscription (from the trial or anew), change its plan or
 * period, cancel it at the end of the period or at once, take a cancellation back, and close a tenant.
 */
class SubscriptionController extends Controller
{
    public function start(SubscriptionRequest $request, Tenant $tenant, StartSubscription $start): RedirectResponse
    {
        return $this->attempt($tenant, fn (): Subscription => $start->handle($tenant, Plan::query()->findOrFail((int) $request->validated('plan_id')),
            BillingPeriod::from((string) $request->validated('billing_period')), $request->user('platform')), __('The subscription has started and its first invoice is issued.'));
    }

    public function change(SubscriptionRequest $request, Tenant $tenant, ChangeSubscription $change): RedirectResponse
    {
        $subscription = Subscription::currentFor($tenant->id) ?? abort(404);
        $over = [];
        $redirect = $this->attempt($tenant, function () use (&$over, $request, $subscription, $change): void {
            $over = $change->handle($subscription, Plan::query()->findOrFail((int) $request->validated('plan_id')), BillingPeriod::from((string) $request->validated('billing_period')), $request->user('platform'));
        }, __('The subscription is changed; the new price applies from the next invoice.'));

        return $over === [] ? $redirect : $redirect->with('warning', implode(' ', $over));
    }

    public function cancel(ReasonRequest $request, Tenant $tenant, CancelSubscription $cancel): RedirectResponse
    {
        $subscription = Subscription::currentFor($tenant->id) ?? abort(404);

        return $this->attempt($tenant, fn (): Subscription => $cancel->handle($subscription, (string) $request->validated('reason'), $request->user('platform'), $request->boolean('immediately')),
            $request->boolean('immediately') ? __(':tenant is cancelled.', ['tenant' => $tenant->name]) : __('The subscription ends at the end of its period.'));
    }

    public function resume(Request $request, Tenant $tenant, ResumeSubscription $resume): RedirectResponse
    {
        $subscription = Subscription::currentFor($tenant->id) ?? abort(404);

        return $this->attempt($tenant, fn (): Subscription => $resume->handle($subscription, $request->user('platform')), __('The cancellation is taken back.'));
    }

    public function close(ReasonRequest $request, Tenant $tenant, CancelTenant $cancel): RedirectResponse
    {
        return $this->attempt($tenant, fn (): Tenant => $cancel->handle($tenant, (string) $request->validated('reason'), $request->user('platform')), __(':tenant is cancelled.', ['tenant' => $tenant->name]));
    }

    private function attempt(Tenant $tenant, callable $step, string $success): RedirectResponse
    {
        try {
            $step();
        } catch (PlatformRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('platform.tenants.show', $tenant)->with('success', $success);
    }
}
