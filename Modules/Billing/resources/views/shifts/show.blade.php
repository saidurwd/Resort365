@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp
<x-layouts::print :title="__('Cashier shift report')">
    <div class="d-flex justify-content-between align-items-start mb-4" data-shift-report="{{ $shift->id }}">
        <div>
            <h1 class="h4 mb-1">{{ $property?->name }}</h1>
            <div class="text-body-secondary small">{{ $property?->address }}</div>
        </div>
        <div class="text-end">
            <div class="h5 mb-0">{{ $shift->isOpen() ? __('Shift report (X, shift still open)') : __('Shift report (Z)') }}</div>
            <div>{{ __('Business date') }} {{ $shift->business_date->format('d M Y') }}</div>
        </div>
    </div>

    <table class="table table-bordered table-sm">
        <tr><th class="w-25">{{ __('Cashier') }}</th><td>{{ $cashier }}</td><th class="w-25">{{ __('Status') }}</th><td>{{ $shift->status->label() }}</td></tr>
        <tr><th>{{ __('Opened') }}</th><td>{{ $shift->opened_at->format('d M Y H:i') }}</td><th>{{ __('Closed') }}</th><td>{{ $shift->closed_at?->format('d M Y H:i') }}@if ($closedBy) · {{ $closedBy }}@endif</td></tr>
    </table>

    <h2 class="h6 mt-4">{{ __('Cash') }}</h2>
    <table class="table table-sm">
        <tr><td>{{ __('Opening float') }}</td><td class="text-end">{{ $money($shift->opening_float) }}</td></tr>
        <tr><td>{{ __('Cash received') }}</td><td class="text-end">{{ $money($received) }}</td></tr>
        <tr><td>{{ __('Cash refunded') }}</td><td class="text-end">{{ (float) $refunded > 0 ? '−' : '' }}{{ $money($refunded) }}</td></tr>
        <tr class="fw-semibold"><td>{{ __('Expected cash') }}</td><td class="text-end" data-expected>{{ $money($expected) }}</td></tr>
        @unless ($shift->isOpen())
            <tr class="fw-semibold"><td>{{ __('Counted cash') }}</td><td class="text-end" data-counted>{{ $money($shift->counted_cash) }}</td></tr>
            <tr class="fw-semibold"><td>{{ __('Variance (over + / short −)') }}</td><td class="text-end" data-variance>{{ $money($shift->cash_variance) }}</td></tr>
            @if ($shift->variance_reason)
                <tr><td>{{ __('Reason') }}</td><td class="text-end">{{ $shift->variance_reason }}</td></tr>
            @endif
        @endunless
    </table>

    @if ($shift->denominations)
        <h2 class="h6 mt-4">{{ __('Count') }}</h2>
        <table class="table table-sm">
            @foreach ($shift->denominations as $value => $count)
                <tr><td>{{ $value }} × {{ $count }}</td><td class="text-end">{{ $money((string) ((float) $value * $count)) }}</td></tr>
            @endforeach
        </table>
    @endif

    <h2 class="h6 mt-4">{{ __('Payments') }}</h2>
    <table class="table table-sm">
        <thead><tr><th>{{ __('Receipt') }}</th><th>{{ __('Time') }}</th><th>{{ __('Type') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->receipt_no }}</td>
                    <td>{{ $payment->received_at->format('H:i') }}</td>
                    <td>{{ $payment->payment_type->label() }}</td>
                    <td>{{ $payment->method->label() }}</td>
                    <td class="text-end">{{ $payment->payment_type === \Modules\Billing\Enums\PaymentType::Refund ? '−' : '' }}{{ $money($payment->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('No payments in this shift.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="row mt-5">
        <div class="col-6"><div class="border-top pt-1 small">{{ __('Cashier') }}</div></div>
        <div class="col-6"><div class="border-top pt-1 small">{{ __('Supervisor') }}</div></div>
    </div>
</x-layouts::print>
