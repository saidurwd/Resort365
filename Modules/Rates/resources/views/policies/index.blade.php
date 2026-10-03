@php($money = fn (?string $amount): string => number_format((float) $amount, 2))

<x-layouts::app :title="__('Policies')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Policies') => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Deposit policies')" icon="bi-piggy-bank" body-class="p-0">
                @can('create', \Modules\Rates\Models\DepositPolicy::class)
                    <x-slot:tools><a href="{{ route('rates.deposit-policies.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New deposit policy') }}</a></x-slot:tools>
                @endcan
                @forelse ($depositPolicies as $policy)
                    <div class="d-flex gap-3 px-3 py-2 border-bottom" data-deposit-policy>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $policy->name }} @if ($policy->is_default)<span class="badge text-bg-primary">{{ __('Default') }}</span>@endif</div>
                            <div class="small">
                                @switch($policy->type)
                                    @case(\Modules\Rates\Enums\DepositType::Percentage)
                                        {{ __(':default% of the total', ['default' => rtrim(rtrim($policy->default_percent, '0'), '.')]) }}
                                        @if ($policy->min_percent !== null || $policy->max_percent !== null)
                                            <span class="text-body-secondary">({{ __('staff may choose :min–:max%', ['min' => rtrim(rtrim($policy->min_percent ?? '0.00', '0'), '.'), 'max' => rtrim(rtrim($policy->max_percent ?? '100.00', '0'), '.')]) }})</span>
                                        @else
                                            <span class="text-body-secondary">({{ __('negotiable per booking') }})</span>
                                        @endif
                                        @break
                                    @case(\Modules\Rates\Enums\DepositType::FixedAmount)
                                        {{ $money($policy->fixed_amount) }} {{ $currency }}
                                        @break
                                    @default
                                        {{ $policy->type->label() }}
                                @endswitch
                                · {{ __('due within :time', ['time' => \Carbon\CarbonInterval::minutes($policy->due_within_minutes)->cascade()->forHumans()]) }}
                                · {{ $policy->auto_cancel_unpaid ? __('unpaid bookings cancel automatically') : __('no automatic cancellation') }}
                            </div>
                            <div class="small text-body-secondary">
                                {{ __('Balance: :rule', ['rule' => $policy->balance_due_rule === \Modules\Rates\Enums\BalanceDueRule::AtCheckIn ? __('at check-in') : trans_choice(':count day before arrival|:count days before arrival', (int) $policy->balance_due_days)]) }}
                                @if ($policy->full_payment_within_hours) · {{ __('full payment if arriving within :hours hours', ['hours' => $policy->full_payment_within_hours]) }}@endif
                                @if ($plans = $plansUsing('deposit_policy_id', $policy->id)) · {{ __('Used by :plans', ['plans' => implode(', ', $plans)]) }}@endif
                            </div>
                        </div>
                        <div class="text-nowrap">
                            @can('update', $policy)
                                <a href="{{ route('rates.deposit-policies.edit', $policy) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-delete :action="route('rates.deposit-policies.destroy', $policy)" icon-only :title="__('Delete :name?', ['name' => $policy->name])" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="bi-piggy-bank" :title="__('No deposit policies yet')" class="py-4" />
                @endforelse
            </x-card>

            <x-card :title="__('Cancellation policies')" icon="bi-x-octagon" body-class="p-0">
                @can('create', \Modules\Rates\Models\CancellationPolicy::class)
                    <x-slot:tools><a href="{{ route('rates.cancellation-policies.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New cancellation policy') }}</a></x-slot:tools>
                @endcan
                @forelse ($cancellationPolicies as $policy)
                    <div class="d-flex gap-3 px-3 py-2 border-bottom" data-cancellation-policy>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $policy->name }} @if ($policy->is_default)<span class="badge text-bg-primary">{{ __('Default') }}</span>@endif</div>
                            <ul class="small mb-1 ps-3">
                                @foreach ($policy->rules as $rule)
                                    <li>{{ $rule->windowLabel() }}: {{ (float) $rule->charge_value === 0.0 ? __('free (full refund)') : $rule->charge_type->describe($rule->charge_value) }}</li>
                                @endforeach
                                <li>{{ __('No-show') }}: {{ $policy->no_show_charge_type ? $policy->no_show_charge_type->describe((string) $policy->no_show_charge_value) : __('as on the arrival day') }}</li>
                            </ul>
                            @if ($plans = $plansUsing('cancellation_policy_id', $policy->id))<div class="small text-body-secondary">{{ __('Used by :plans', ['plans' => implode(', ', $plans)]) }}</div>@endif
                        </div>
                        <div class="text-nowrap">
                            @can('update', $policy)
                                <a href="{{ route('rates.cancellation-policies.edit', $policy) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-delete :action="route('rates.cancellation-policies.destroy', $policy)" icon-only :title="__('Delete :name?', ['name' => $policy->name])" />
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="bi-x-octagon" :title="__('No cancellation policies yet')" class="py-4" />
                @endforelse
            </x-card>
        </div>

        <div class="col-xl-4">
            <x-card :title="__('Try it')" icon="bi-calculator">
                <form method="GET" action="{{ route('rates.policies.index') }}" data-policy-try>
                    <x-form.money name="total" :label="__('Booking total (incl. taxes)')" :currency="$currency" :value="request('total', '64515.00')" required />
                    <div class="row">
                        <div class="col-6"><x-form.input name="nights" type="number" min="1" :label="__('Nights')" :value="request('nights', 3)" /></div>
                        <div class="col-6"><x-form.input name="percent" :label="__('Deposit %')" :value="request('percent')" :help="__('Empty = default')" /></div>
                    </div>
                    <x-form.date name="arrival" :label="__('Arrival')" :value="request('arrival', now()->addDays(30)->toDateString())" required />
                    <x-form.date name="cancel_on" :label="__('Cancelled on')" :value="request('cancel_on')" />
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="no_show" value="1" id="field-no_show" @checked(request()->boolean('no_show'))>
                        <label class="form-check-label" for="field-no_show">{{ __('No-show instead') }}</label>
                    </div>
                    <x-form.select name="deposit_policy" :label="__('Deposit policy')" :options="$depositPolicies->pluck('name', 'id')->all()" :value="$tryDeposit?->id" :search="false" />
                    <x-form.select name="cancellation_policy" :label="__('Cancellation policy')" :options="$cancellationPolicies->pluck('name', 'id')->all()" :value="$tryCancellation?->id" :search="false" />
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('Calculate') }}</button>
                </form>

                @if ($deposit)
                    <table class="table table-sm mt-3 mb-0" data-policy-result>
                        <tr><td>{{ __('Deposit (:percent%)', ['percent' => rtrim(rtrim($deposit->percent, '0'), '.')]) }}</td><td class="text-end font-monospace" data-deposit>{{ $money($deposit->amount) }}</td></tr>
                        <tr><td>{{ __('Balance (due :date)', ['date' => \Carbon\Carbon::parse($deposit->balanceDueOn)->format('d M Y')]) }}</td><td class="text-end font-monospace">{{ $money($deposit->balance) }}</td></tr>
                        @if ($deposit->dueAt)
                            <tr><td colspan="2" class="small text-body-secondary">{{ __('Deposit due by :time if booked now', ['time' => \Carbon\Carbon::parse($deposit->dueAt)->format('d M Y H:i')]) }}@if ($deposit->fullPaymentRequired) · {{ __('full payment: arrival is soon') }}@endif</td></tr>
                        @endif
                        @if ($cancellation)
                            <tr class="table-group-divider"><td>{{ $cancellation->noShow ? __('No-show fee') : __('Cancellation fee') }}</td><td class="text-end font-monospace text-danger" data-fee>{{ $money($cancellation->fee) }}</td></tr>
                            <tr><td>{{ __('Refund of the deposit') }}</td><td class="text-end font-monospace" data-refund>{{ $money($cancellation->refund) }}</td></tr>
                            @if ((float) $cancellation->owed > 0)<tr><td>{{ __('Still owed') }}</td><td class="text-end font-monospace">{{ $money($cancellation->owed) }}</td></tr>@endif
                        @endif
                    </table>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
