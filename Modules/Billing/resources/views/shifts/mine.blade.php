@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $currency = $property?->currencyCode;
@endphp
<x-layouts::app :title="__('My cashier shift')" :subtitle="$property?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Billing') => null, __('My cashier shift') => null]">
    @if (! $shift)
        <div class="row">
            <div class="col-lg-5">
                <x-card :title="__('Open a shift')" icon="bi-cash-coin">
                    <p class="text-body-secondary">{{ __('Count the cash float in your drawer. Every payment and refund you take until you close the shift belongs to it.') }}</p>
                    <form method="POST" action="{{ route('billing.shifts.open') }}" data-open-shift>
                        @csrf
                        <x-form.money name="opening_float" :label="__('Opening float')" :currency="$currency" value="0.00" required />
                        <button type="submit" class="btn btn-primary"><i class="bi bi-unlock"></i> {{ __('Open shift') }}</button>
                    </form>
                </x-card>
            </div>
            <div class="col-lg-7">
                <x-card :title="__('My recent shifts')" icon="bi-clock-history" body-class="p-0">
                    @forelse ($recent as $past)
                        <a href="{{ route('billing.shifts.show', $past) }}" class="d-flex justify-content-between px-3 py-2 border-bottom text-decoration-none text-body">
                            <span>{{ $past->business_date->format('D d M Y') }} · {{ $past->opened_at->format('H:i') }}–{{ $past->closed_at?->format('H:i') }}</span>
                            <span @class(['font-monospace', 'text-danger' => (float) $past->cash_variance < 0, 'text-warning-emphasis' => (float) $past->cash_variance > 0])>
                                {{ __('Variance') }} {{ $money($past->cash_variance) }}
                            </span>
                        </a>
                    @empty
                        <x-empty-state icon="bi-cash-coin" :title="__('No shifts yet')" />
                    @endforelse
                </x-card>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-lg-5">
                <x-card :title="__('Shift in progress')" icon="bi-cash-coin" data-shift-open="{{ $shift->id }}">
                    <dl class="row mb-0">
                        <dt class="col-7">{{ __('Opened') }}</dt><dd class="col-5 text-end">{{ $shift->opened_at->format('d M H:i') }}</dd>
                        <dt class="col-7">{{ __('Business date') }}</dt><dd class="col-5 text-end">{{ $shift->business_date->format('d M Y') }}</dd>
                        <dt class="col-7">{{ __('Opening float') }}</dt><dd class="col-5 text-end font-monospace">{{ $money($shift->opening_float) }}</dd>
                        <dt class="col-7">{{ __('Cash received') }}</dt><dd class="col-5 text-end font-monospace">{{ $money($received) }}</dd>
                        <dt class="col-7">{{ __('Cash refunded') }}</dt><dd class="col-5 text-end font-monospace">{{ (float) $refunded > 0 ? '−' : '' }}{{ $money($refunded) }}</dd>
                        <dt class="col-7 border-top pt-2">{{ __('Expected in the drawer') }}</dt><dd class="col-5 text-end font-monospace fw-semibold border-top pt-2" data-expected>{{ $currency }} {{ $money($expected) }}</dd>
                    </dl>
                </x-card>

                <x-card :title="__('Payments in this shift')" icon="bi-receipt" body-class="p-0">
                    @forelse ($payments as $payment)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom small">
                            <span>{{ $payment->receipt_no }} · {{ $payment->method->label() }} · {{ $payment->payment_type->label() }}</span>
                            <span class="font-monospace">{{ $payment->payment_type === \Modules\Billing\Enums\PaymentType::Refund ? '−' : '' }}{{ $money($payment->amount) }}</span>
                        </div>
                    @empty
                        <p class="text-body-secondary px-3 py-2 mb-0">{{ __('No payments yet.') }}</p>
                    @endforelse
                </x-card>
            </div>
            <div class="col-lg-7">
                <x-card :title="__('Count the cash and close')" icon="bi-calculator">
                    <form method="POST" action="{{ route('billing.shifts.close', $shift) }}" data-close-shift data-confirm="{{ __('Close the shift?') }}"
                        x-data="{ counts: {}, values: @js($denominations), get total() { return this.values.reduce((sum, value) => sum + Number(value) * (Number(this.counts[value]) || 0), 0); } }">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-3">
                                <thead><tr><th>{{ __('Note / coin') }}</th><th class="text-end">{{ __('Count') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($denominations as $value)
                                        <tr>
                                            <td class="font-monospace">{{ $value }}</td>
                                            <td class="text-end">
                                                <label class="visually-hidden" for="count-{{ $loop->index }}">{{ __('How many of :value', ['value' => $value]) }}</label>
                                                <input type="number" min="0" step="1" id="count-{{ $loop->index }}" name="count[{{ $value }}]" value="{{ old('count.'.$value) }}"
                                                    class="form-control form-control-sm text-end shift-count-input" x-model="counts['{{ $value }}']">
                                            </td>
                                            <td class="text-end font-monospace" x-text="((Number(counts['{{ $value }}']) || 0) * {{ $value }}).toFixed(2)">0.00</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr><th colspan="2">{{ __('Counted') }}</th><th class="text-end font-monospace" x-text="total.toFixed(2)" data-counted>0.00</th></tr>
                                    <tr><th colspan="2">{{ __('Expected') }}</th><th class="text-end font-monospace">{{ $money($expected) }}</th></tr>
                                </tfoot>
                            </table>
                        </div>
                        <x-form.input name="variance_reason" :label="__('Reason for any difference')" :help="__('Required when the count does not match the expected cash.')" />
                        <button type="submit" class="btn btn-danger"><i class="bi bi-lock"></i> {{ __('Close shift') }}</button>
                    </form>
                </x-card>
            </div>
        </div>
    @endif
</x-layouts::app>
