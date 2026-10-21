<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Platform\Enums\PlatformInvoiceStatus;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;
use Throwable;

/**
 * The daily billing run (Step 7.4, `platform:billing`): ended trials start their paid period with its first invoice, ended periods
 * renew with the next invoice, a subscription that was cancelled ends and its tenant is cancelled, and a tenant with an invoice past
 * its due date is suspended until it pays. Each tenant is handled on its own, so one failure does not stop the others; running it
 * twice the same day does nothing more.
 */
class RunBilling extends Action
{
    /**
     * @return array{started: int, renewed: int, cancelled: int, suspended: int, failed: int}
     */
    public function handle(): array
    {
        $today = CarbonImmutable::today();
        $counts = ['started' => 0, 'renewed' => 0, 'cancelled' => 0, 'suspended' => 0, 'failed' => 0];

        $trials = Subscription::query()->with(['tenant', 'plan'])->where('status', SubscriptionStatus::Trialing->value)->where('trial_ends_at', '<=', now())->get();

        foreach ($trials as $subscription) {
            $this->attempt($counts, fn (): string => $this->endTrial($subscription));
        }

        $ended = Subscription::query()->with(['tenant', 'plan'])->where('status', SubscriptionStatus::Active->value)->whereDate('current_period_end', '<', $today)->get();

        foreach ($ended as $subscription) {
            $this->attempt($counts, fn (): string => $this->renew($subscription, $today));
        }

        $overdue = PlatformInvoice::query()->where('status', PlatformInvoiceStatus::Open->value)->whereDate('due_on', '<', $today)->distinct()->pluck('tenant_id');

        foreach (Tenant::query()->whereKey($overdue)->whereIn('status', [TenantStatus::Active->value, TenantStatus::Trial->value])->get() as $tenant) {
            $this->attempt($counts, function () use ($tenant): string {
                SuspendTenant::make()->handle($tenant, __('An invoice is overdue.'), null, 'unpaid');

                return 'suspended';
            });
        }

        return $counts;
    }

    private function endTrial(Subscription $subscription): string
    {
        if ($subscription->cancel_at_period_end) {
            CancelTenant::make()->handle($subscription->tenant, (string) $subscription->cancel_reason);

            return 'cancelled';
        }

        if (! $subscription->tenant->status->canAccess()) {
            return 'skipped';
        }

        StartSubscription::make()->handle($subscription->tenant);

        return 'started';
    }

    private function renew(Subscription $subscription, CarbonImmutable $today): string
    {
        if ($subscription->cancel_at_period_end) {
            CancelTenant::make()->handle($subscription->tenant, (string) $subscription->cancel_reason);

            return 'cancelled';
        }

        return $this->transaction(function () use ($subscription, $today): string {
            // One invoice per period missed, so a run that did not happen for a while catches up.
            while ($subscription->current_period_end !== null && $subscription->current_period_end->lt($today)) {
                $start = CarbonImmutable::parse($subscription->current_period_end)->addDay();
                $end = $subscription->billing_period->periodEnd($start);
                $subscription->fill(['current_period_start' => $start, 'current_period_end' => $end])->save();
                IssuePlatformInvoice::make()->handle($subscription, $start, $end);
            }

            return 'renewed';
        });
    }

    /**
     * @param  array{started: int, renewed: int, cancelled: int, suspended: int, failed: int}  $counts
     * @param  callable(): string  $step
     */
    private function attempt(array &$counts, callable $step): void
    {
        try {
            $outcome = $step();

            if (isset($counts[$outcome])) {
                $counts[$outcome]++;
            }
        } catch (Throwable $exception) {
            report($exception);
            $counts['failed']++;
        }
    }
}
