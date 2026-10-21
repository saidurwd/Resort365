<x-layouts::platform :title="$tenant->name" :subtitle="$tenant->domain()" :breadcrumbs="[__('Platform') => route('platform.console'), __('Tenants') => route('platform.tenants.index'), $tenant->name => null]">
    <div class="row">
        <div class="col-lg-7">
            <x-card :title="__('Usage')" icon="bi-bar-chart">
                <div data-usage-list>
                    @foreach ($usage as $name => $row)
                        <div class="mb-3" data-meter="{{ $name }}">
                            <div class="d-flex justify-content-between small"><span>{{ $row['label'] }}</span>
                                <span class="font-monospace">{{ number_format((float) $row['used'], $row['unit'] === 'GB' ? 2 : 0) }} @if ($row['limit'] !== null) / {{ number_format((float) $row['limit'], 0) }} @else <span class="text-body-secondary">{{ __('no limit') }}</span> @endif {{ $row['unit'] === 'GB' ? 'GB' : '' }}</span></div>
                            @if ($row['percent'] !== null)
                                <progress class="w-100" max="100" value="{{ $row['percent'] }}" data-percent="{{ $row['percent'] }}">{{ $row['percent'] }}%</progress>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card :title="__('Sign in as one of its people')" icon="bi-person-badge">
                @if ($people === [])
                    <p class="text-body-secondary mb-0" data-no-people>{{ $tenant->status->canAccess() ? __('No active users.') : __('The tenant is not active, so nobody can sign in to it.') }}</p>
                @else
                    <form method="POST" action="{{ route('platform.tenants.impersonate', $tenant) }}" class="row g-2 align-items-end" data-impersonate-form>
                        @csrf
                        <div class="col-md-4"><x-form.select name="user_id" :label="__('Person')" :options="collect($people)->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email])->all()" required :search="false" wrapper-class="mb-0" /></div>
                        <div class="col-md-5"><x-form.input name="reason" :label="__('Why')" required wrapper-class="mb-0" /></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-outline-danger w-100" data-impersonate>{{ __('Sign in as') }}</button></div>
                    </form>
                    <p class="small text-body-secondary mt-2 mb-0">{{ __('You go to the tenant\'s own address with a link that works once, for a minute. Everything you do there is recorded against your name.') }}</p>
                @endif
            </x-card>

            <x-card :title="__('Recent sign-ins as a user')" icon="bi-clock-history" body-class="p-0">
                <table class="table table-sm mb-0" data-sessions>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr><td class="ps-3">{{ $session->admin?->name }} → {{ $session->user_name }}<div class="small text-body-secondary">{{ $session->reason }}</div></td><td class="small">{{ $session->started_at?->format('d M H:i') ?? __('Not used') }}@if ($session->ended_at) – {{ $session->ended_at->format('H:i') }} @endif</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Never.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card :title="__('Status')" icon="bi-activity">
                <p class="mb-2"><x-status-badge :status="$tenant->status" /> @if ($tenant->trial_ends_at) <span class="small text-body-secondary">{{ __('Trial ends :date', ['date' => $tenant->trial_ends_at->format('d M Y')]) }}</span> @endif</p>
                @if ($tenant->status === \App\Enums\TenantStatus::Suspended)
                    <form method="POST" action="{{ route('platform.tenants.reactivate', $tenant) }}">@csrf<button type="submit" class="btn btn-success" data-reactivate>{{ __('Reactivate') }}</button></form>
                @elseif ($tenant->status->canAccess())
                    <form method="POST" action="{{ route('platform.tenants.suspend', $tenant) }}" class="d-flex gap-2" data-suspend-form data-confirm="{{ __('Suspend :tenant?', ['tenant' => $tenant->name]) }}" data-confirm-text="{{ __('Its people see the suspended page until you reactivate it.') }}" data-confirm-variant="danger">
                        @csrf
                        <input type="text" name="reason" class="form-control" placeholder="{{ __('Reason') }}" required>
                        <button type="submit" class="btn btn-outline-danger" data-suspend>{{ __('Suspend') }}</button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Plan')" icon="bi-box-seam">
                <form method="POST" action="{{ route('platform.tenants.plan', $tenant) }}" data-plan-form>
                    @csrf @method('PUT')
                    <x-form.select name="plan_id" :label="__('Moves the tenant to')" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->is_active ? '' : ' ('.__('inactive').')')])->all()" :value="$plan?->id" :placeholder="__('No plan (every module, no limits)')" :search="false" />
                    <button type="submit" class="btn btn-primary" data-change-plan>{{ __('Apply the plan') }}</button>
                </form>
                @if ($plan)
                    <p class="small text-body-secondary mt-2 mb-0" data-plan-modules>{{ __('Modules: :list', ['list' => implode(', ', array_map(fn ($m) => \Modules\Platform\Services\ModuleCatalog::labels()[$m] ?? $m, $plan->modules))]) }}</p>
                @endif
            </x-card>

            <x-card :title="__('Subscription')" icon="bi-credit-card" data-subscription-card>
                @if ($subscription && $subscription->status !== \Modules\Platform\Enums\SubscriptionStatus::Cancelled)
                    <p class="mb-2">{{ $subscription->plan->name }} · {{ $subscription->billing_period->label() }} <x-status-badge :status="$subscription->status" />
                        @if ($subscription->endsAt())<span class="small text-body-secondary">{{ $subscription->status === \Modules\Platform\Enums\SubscriptionStatus::Trialing ? __('trial ends :date', ['date' => $subscription->endsAt()->format('d M Y')]) : __('renews :date', ['date' => $subscription->endsAt()->format('d M Y')]) }}</span>@endif
                    </p>
                    @if ($subscription->cancel_at_period_end)
                        <div class="alert alert-warning py-2 d-flex justify-content-between align-items-center gap-2">
                            <span>{{ __('Cancelled: :reason', ['reason' => $subscription->cancel_reason]) }}</span>
                            <form method="POST" action="{{ route('platform.tenants.subscription.resume', $tenant) }}">@csrf<button type="submit" class="btn btn-sm btn-warning" data-resume>{{ __('Take back') }}</button></form>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('platform.tenants.subscription.'.($subscription->status === \Modules\Platform\Enums\SubscriptionStatus::Trialing ? 'start' : 'change'), $tenant) }}" class="row g-2 align-items-end mb-3" data-subscription-form>
                        @csrf @if ($subscription->status !== \Modules\Platform\Enums\SubscriptionStatus::Trialing) @method('PUT') @endif
                        <div class="col-6"><x-form.select name="plan_id" :label="__('Plan')" :options="$plans->where('is_active', true)->mapWithKeys(fn ($p) => [$p->id => $p->name])->all()" :value="$subscription->plan_id" :search="false" wrapper-class="mb-0" required /></div>
                        <div class="col-6"><x-form.select name="billing_period" :label="__('Billed')" :options="$periods" :value="$subscription->billing_period->value" :search="false" wrapper-class="mb-0" required /></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">{{ $subscription->status === \Modules\Platform\Enums\SubscriptionStatus::Trialing ? __('End the trial and start billing') : __('Change the subscription') }}</button></div>
                    </form>
                    @unless ($subscription->cancel_at_period_end)
                        <form method="POST" action="{{ route('platform.tenants.subscription.cancel', $tenant) }}" class="row g-2 align-items-center" data-cancel-subscription data-confirm="{{ __('Cancel the subscription of :tenant?', ['tenant' => $tenant->name]) }}" data-confirm-variant="danger">
                            @csrf
                            <div class="col-12"><input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}" required></div>
                            <div class="col-auto form-check ms-2"><input type="checkbox" class="form-check-input" name="immediately" value="1" id="field-immediately"><label class="form-check-label small" for="field-immediately">{{ __('At once, not at the end of the period') }}</label></div>
                            <div class="col-auto ms-auto"><button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Cancel the subscription') }}</button></div>
                        </form>
                    @endunless
                @elseif ($tenant->status->canAccess())
                    <p class="text-body-secondary small">{{ __('No subscription: nothing is billed. Start one to bill this tenant from today.') }}</p>
                    <form method="POST" action="{{ route('platform.tenants.subscription.start', $tenant) }}" class="row g-2 align-items-end" data-subscription-form>
                        @csrf
                        <div class="col-6"><x-form.select name="plan_id" :label="__('Plan')" :options="$plans->where('is_active', true)->mapWithKeys(fn ($p) => [$p->id => $p->name])->all()" :value="$plan?->id" :search="false" wrapper-class="mb-0" required /></div>
                        <div class="col-6"><x-form.select name="billing_period" :label="__('Billed')" :options="$periods" value="monthly" :search="false" wrapper-class="mb-0" required /></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">{{ __('Start the subscription') }}</button></div>
                    </form>
                @else
                    <p class="text-body-secondary mb-0">{{ __('No active subscription.') }}</p>
                @endif

                @if ($tenant->status !== \App\Enums\TenantStatus::Cancelled)
                    <form method="POST" action="{{ route('platform.tenants.close', $tenant) }}" class="d-flex gap-2 mt-3 pt-3 border-top" data-close-tenant data-confirm="{{ __('Close :tenant now?', ['tenant' => $tenant->name]) }}" data-confirm-text="{{ __('Its address stops working. Its data is kept for :days days.', ['days' => config('platform.billing.retention_days')]) }}" data-confirm-variant="danger">
                        @csrf
                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason for closing') }}" aria-label="{{ __('Reason for closing') }}" required>
                        <button type="submit" class="btn btn-sm btn-danger text-nowrap">{{ __('Close the tenant') }}</button>
                    </form>
                @elseif ($tenant->cancelled_at)
                    <p class="small text-body-secondary mt-3 mb-0">{{ __('Closed :date; data kept until :until.', ['date' => $tenant->cancelled_at->format('d M Y'), 'until' => $tenant->cancelled_at->addDays((int) config('platform.billing.retention_days'))->format('d M Y')]) }}</p>
                @endif
            </x-card>

            <x-card :title="__('Invoices')" icon="bi-receipt" body-class="p-0">
                <table class="table table-sm mb-0" data-tenant-invoices>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td class="ps-3"><a href="{{ route('platform.invoices.show', $invoice) }}">{{ $invoice->number }}</a><div class="small text-body-secondary">{{ $invoice->issued_on->format('d M Y') }}</div></td>
                                <td class="text-end">{{ $invoice->currency_code }} {{ $invoice->amount }}</td>
                                <td class="pe-3 text-end"><x-status-badge :status="$invoice->status" />@if ($invoice->isOverdue()) <span class="badge text-bg-danger">{{ __('Overdue') }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card :title="__('Data export')" icon="bi-file-earmark-zip">
                <form method="POST" action="{{ route('platform.tenants.exports.store', $tenant) }}" class="mb-2" data-export-form>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Export all of its data') }}</button>
                </form>
                @foreach ($exports as $export)
                    <div class="d-flex justify-content-between align-items-center border-top py-2 small" data-export="{{ $export->id }}">
                        <span>{{ $export->requestedAt->format('d M Y H:i') }} · {{ $export->requestedBy }} <span class="badge text-bg-{{ $export->statusColor }}">{{ $export->statusLabel }}</span></span>
                        @if ($export->ready())<a href="{{ route('platform.tenants.exports.download', [$tenant, $export->id]) }}" class="btn btn-sm btn-primary">{{ __('Download') }}</a>@endif
                    </div>
                @endforeach
            </x-card>

            <x-card :title="__('Audit')" icon="bi-journal-text" body-class="p-0">
                <table class="table table-sm mb-0" data-tenant-audit>
                    <tbody>
                        @forelse ($audit as $entry)
                            <tr><td class="ps-3">{{ $entry->summary }}<div class="small text-body-secondary">{{ $entry->admin_name }}</div></td><td class="small pe-3">{{ $entry->created_at->format('d M H:i') }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts::platform>
