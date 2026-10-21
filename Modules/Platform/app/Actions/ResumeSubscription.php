<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Takes back a cancellation that has not taken effect yet (Step 7.4): the subscription renews as before.
 */
class ResumeSubscription extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Subscription $subscription, ?PlatformAdmin $admin = null): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Cancelled || ! $subscription->cancel_at_period_end) {
            throw new PlatformRuleViolated(__('The subscription is not waiting to be cancelled.'));
        }

        $subscription->fill(['cancel_at_period_end' => false, 'cancel_reason' => null])->save();
        $this->audit->log($admin, 'subscription.resumed', __('Cancellation taken back'), $subscription->tenant_id, ['subscription' => $subscription->id]);

        return $subscription;
    }
}
