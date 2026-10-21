{{--
    Reminds the people who see the subscription (Step 7.4) that the trial ends soon, an invoice is overdue, or the company is closing.
--}}
@auth('web')
    @can('core.subscription.view')
        @php($subscription = app(\Modules\Core\Contracts\SubscriptionAccount::class)->current())
        @if ($subscription?->hasOverdue())
            <div class="alert alert-danger rounded-0 mb-0 py-2" role="alert" data-subscription-banner="overdue">
                {{ __('An invoice of :currency :amount is overdue. Pay it to keep using the system.', ['currency' => $subscription->currencyCode, 'amount' => $subscription->overdue]) }}
                <a href="{{ route('core.subscription.show') }}" class="alert-link">{{ __('See the invoices') }}</a>
            </div>
        @elseif ($subscription?->cancelAtPeriodEnd)
            <div class="alert alert-warning rounded-0 mb-0 py-2" role="alert" data-subscription-banner="closing">
                {{ __('Your subscription is cancelled; the system closes on :date.', ['date' => $subscription->endsAt?->format('d M Y')]) }}
                <a href="{{ route('core.subscription.show') }}" class="alert-link">{{ __('Keep it or export your data') }}</a>
            </div>
        @elseif ($subscription?->remindTrial)
            <div class="alert alert-info rounded-0 mb-0 py-2" role="status" data-subscription-banner="trial">
                {{ __('Your free trial ends on :date. Your subscription then starts with the :plan plan.', ['date' => $subscription->endsAt?->subDay()->format('d M Y'), 'plan' => $subscription->planName]) }}
                <a href="{{ route('core.subscription.show') }}" class="alert-link">{{ __('Subscription') }}</a>
            </div>
        @endif
    @endcan
@endauth
