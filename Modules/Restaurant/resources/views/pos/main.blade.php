@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp
<x-layouts::pos :title="__('POS')" :terminal="$terminal">
    <x-slot:status>
        <span class="pos-bar__meta"><i class="bi bi-calendar3"></i> {{ ($session?->business_date ?? now())->format('D d M') }}</span>
        @if ($session)
            <span class="badge text-bg-success pos-bar__badge" data-session-open>{{ __('Session open') }}</span>
        @else
            <span class="badge text-bg-secondary pos-bar__badge">{{ __('No session') }}</span>
        @endif
    </x-slot:status>

    <div x-data="posIdle({{ $autoLockMinutes }})"></div>

    <div class="pos-grid">
        <section class="pos-card">
            <h1 class="h4"><i class="bi bi-cash-coin"></i> {{ __('Cash session') }}</h1>
            @if (! $session)
                @if ($canManage)
                    <form method="POST" action="{{ route('pos.session.open') }}" data-open-session>
                        @csrf
                        <p class="text-body-secondary">{{ __('Count the float in the drawer to start taking payments on this terminal.') }}</p>
                        <x-form.money name="opening_float" :label="__('Opening float')" :currency="$currency" value="0.00" required />
                        <button type="submit" class="btn btn-primary btn-lg pos-btn w-100"><i class="bi bi-unlock"></i> {{ __('Open session') }}</button>
                    </form>
                @else
                    <p class="text-body-secondary">{{ __('No cash session is open on this terminal. A cashier opens it.') }}</p>
                @endif
            @else
                <dl class="row mb-3" data-session-summary>
                    <dt class="col-7">{{ __('Opened') }}</dt><dd class="col-5 text-end">{{ $session->opened_at->inPropertyTime()->format('H:i') }}</dd>
                    <dt class="col-7">{{ __('Float') }}</dt><dd class="col-5 text-end font-monospace">{{ $money($session->opening_float) }}</dd>
                    <dt class="col-7">{{ __('Expected in the drawer') }}</dt><dd class="col-5 text-end font-monospace fw-semibold">{{ $currency }} {{ $money($expected) }}</dd>
                </dl>
                <a href="{{ route('pos.sessions.report', $session) }}" class="btn btn-outline-secondary pos-btn w-100 mb-3" data-x-report><i class="bi bi-receipt"></i> {{ __('X report') }}</a>

                @if ($canManage)
                    <form method="POST" action="{{ route('pos.session.close') }}" data-close-session
                        x-data="managerApproval({ url: @js(route('pos.approvals.store')), action: 'session.close-variance', subjectId: {{ $session->id }},
                            expected: {{ (float) $expected }}, limit: {{ (float) $varianceLimit }}, values: @js($denominations),
                            why: @js(__('The cash difference is larger than :limit.', ['limit' => $money($varianceLimit)])) })"
                        @submit="check($event)">
                        @csrf
                        <input type="hidden" name="approval_id" x-ref="approval">
                        <h2 class="h5">{{ __('Count and close') }}</h2>
                        <div class="pos-count">
                            @foreach ($denominations as $value)
                                <label class="pos-count__row">
                                    <span class="font-monospace">{{ $value }}</span>
                                    <input type="number" min="0" step="1" inputmode="numeric" name="count[{{ $value }}]" class="form-control" x-model="counts['{{ $value }}']" aria-label="{{ __('How many of :value', ['value' => $value]) }}">
                                </label>
                            @endforeach
                        </div>
                        <dl class="row my-2">
                            <dt class="col-7">{{ __('Counted') }}</dt><dd class="col-5 text-end font-monospace" x-text="counted.toFixed(2)" data-counted></dd>
                            <dt class="col-7">{{ __('Difference') }}</dt><dd class="col-5 text-end font-monospace" :class="{ 'text-danger': variance < 0 }" x-text="variance.toFixed(2)" data-variance></dd>
                        </dl>
                        <x-form.input name="variance_reason" :label="__('Reason for any difference')" />
                        @include('restaurant::pos.partials.manager-approval', ['approvers' => $approvers])
                        <button type="submit" class="btn btn-danger btn-lg pos-btn w-100 mt-2" data-close-button><i class="bi bi-lock"></i> {{ __('Close session') }}</button>
                    </form>
                @endif
            @endif
        </section>

        <section class="pos-card">
            <h1 class="h4"><i class="bi bi-receipt-cutoff"></i> {{ __('Orders') }}</h1>
            @if ($canTakeOrders)
                <p class="text-body-secondary">{{ trans_choice(':count order is open in this outlet.|:count orders are open in this outlet.', $openOrders) }}</p>
                <a href="{{ route('pos.floor') }}" class="btn btn-primary btn-lg pos-btn w-100 mb-4" data-floor><i class="bi bi-grid-3x3"></i> {{ __('Tables and orders') }}</a>
            @else
                <p class="text-body-secondary mb-4">{{ __('You cannot take orders.') }}</p>
            @endif
            @if ($recent->isNotEmpty())
                <h2 class="h6">{{ __('Recent sessions here') }}</h2>
                @foreach ($recent as $past)
                    <a href="{{ route('pos.sessions.report', $past) }}" class="d-flex justify-content-between py-2 border-bottom text-decoration-none text-body">
                        <span>{{ $past->business_date->format('d M') }} · {{ $past->closed_at?->inPropertyTime()?->format('H:i') }}</span><span>{{ __('Z report') }}</span>
                    </a>
                @endforeach
            @endif
        </section>
    </div>
</x-layouts::pos>
