@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp

<div class="row">
    <div class="col-xl-7">
        <x-card :title="__('Payments')" icon="bi-credit-card" body-class="p-0">
            @if ($payments->isEmpty())
                <div class="p-3"><x-empty-state :title="__('No payments yet')" :message="__('Take the deposit to confirm the booking.')" icon="bi-credit-card" /></div>
            @else
                <div class="table-responsive">
                    <table class="table mb-0" data-payments>
                        <thead><tr><th class="ps-3">{{ __('Receipt') }}</th><th>{{ __('Received') }}</th><th>{{ __('Method') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="pe-3"></th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr data-payment="{{ $payment->receipt_no }}">
                                    <td class="ps-3 font-monospace text-nowrap">{{ $payment->receipt_no }}</td>
                                    <td class="text-nowrap">{{ $payment->received_at->setTimezone($timezone)->format('d M Y H:i') }}</td>
                                    <td><x-status-badge :status="$payment->method" />@if ($payment->reference) <span class="small text-body-secondary">{{ $payment->reference }}</span>@endif</td>
                                    <td><x-status-badge :status="$payment->payment_type" /></td>
                                    <td class="text-end font-monospace">{{ $money($payment->amount) }}</td>
                                    <td class="pe-3 text-end"><a href="{{ route('billing.payments.receipt', $payment) }}" class="btn btn-sm btn-outline-secondary" data-receipt><i class="bi bi-file-earmark-pdf"></i> {{ __('Receipt') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold"><td class="ps-3" colspan="4">{{ __('Paid') }} ({{ $reservation->currencyCode }})</td><td class="text-end font-monospace">{{ $money($reservation->amountPaid) }}</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
    <div class="col-xl-5">
        @if ($reservation->acceptsPayments() && $reservation->balanceDue !== '0.00')
            @can('create', \Modules\Billing\Models\Payment::class)
                <x-card :title="__('Take a payment')" icon="bi-cash-coin">
                    <p class="small text-body-secondary">
                        {{ __('Deposit :deposit · paid :paid · balance :balance', ['deposit' => $money($reservation->depositRequired), 'paid' => $money($reservation->amountPaid), 'balance' => $money($reservation->balanceDue)]) }}
                    </p>
                    <form method="POST" action="{{ route('billing.payments.store') }}" data-take-payment>
                        @csrf
                        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                        <x-form.select name="method" :label="__('Method')" :search="false" required error-bag="payment"
                            :options="collect($methods)->mapWithKeys(fn ($method) => [$method->value => $method->label()])->all()" :value="'cash'" />
                        <x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :append="$reservation->currencyCode" :value="$suggested" required error-bag="payment" />
                        <x-form.input name="reference" :label="__('Reference')" :help="__('Card slip, bank or wallet transaction number.')" maxlength="100" error-bag="payment" />
                        <x-form.input name="notes" :label="__('Notes')" maxlength="500" error-bag="payment" />
                        <button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> {{ __('Record payment') }}</button>
                    </form>
                </x-card>
            @endcan
        @endif
    </div>
</div>
