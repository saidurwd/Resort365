@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
@endphp
<x-layouts::print :title="$session->isOpen() ? __('X report') : __('Z report')" paper="receipt">
    <div class="text-center mb-2" data-session-report="{{ $session->isOpen() ? 'x' : 'z' }}">
        <div class="fw-bold">{{ $property?->name }}</div>
        <div>{{ $session->outlet->name }} · {{ $session->terminal->name }}</div>
        <div class="fw-bold mt-1">{{ $session->isOpen() ? __('X REPORT (session open)') : __('Z REPORT') }}</div>
        <div>{{ __('Business date') }} {{ $session->business_date->format('d M Y') }}</div>
    </div>
    <table class="table table-sm mb-2">
        <tr><td>{{ __('Opened') }}</td><td class="text-end">{{ $session->opened_at->format('d M H:i') }}</td></tr>
        <tr><td>{{ __('By') }}</td><td class="text-end">{{ $openedBy }}</td></tr>
        @if ($session->closed_at)
            <tr><td>{{ __('Closed') }}</td><td class="text-end">{{ $session->closed_at->format('d M H:i') }}</td></tr>
            <tr><td>{{ __('By') }}</td><td class="text-end">{{ $closedBy }}</td></tr>
        @endif
        <tr><td>{{ __('Opening float') }}</td><td class="text-end">{{ $money($session->opening_float) }}</td></tr>
        <tr><td>{{ __('Cash received') }}</td><td class="text-end">{{ $money($received) }}</td></tr>
        <tr><td>{{ __('Cash refunded') }}</td><td class="text-end">{{ (float) $refunded > 0 ? '−' : '' }}{{ $money($refunded) }}</td></tr>
        <tr class="fw-bold"><td>{{ __('Expected cash') }}</td><td class="text-end" data-expected>{{ $money($expected) }}</td></tr>
        @unless ($session->isOpen())
            <tr class="fw-bold"><td>{{ __('Counted cash') }}</td><td class="text-end" data-counted>{{ $money($session->counted_cash) }}</td></tr>
            <tr class="fw-bold"><td>{{ __('Over (+) / short (−)') }}</td><td class="text-end" data-variance>{{ $money($session->cash_variance) }}</td></tr>
            @if ($session->variance_reason)<tr><td colspan="2">{{ __('Reason') }}: {{ $session->variance_reason }}</td></tr>@endif
            @if ($approvedBy)<tr><td colspan="2" data-approved-by>{{ __('Approved by :name', ['name' => $approvedBy]) }}</td></tr>@endif
        @endunless
    </table>
    @if ($session->denominations)
        <table class="table table-sm mb-2">
            @foreach ($session->denominations as $value => $count)
                <tr><td>{{ $value }} × {{ $count }}</td><td class="text-end">{{ $money((string) ((float) $value * $count)) }}</td></tr>
            @endforeach
        </table>
    @endif
    <p class="text-center small mb-0">{{ __('Sales and payments by method appear here once the POS takes orders.') }}</p>
    <div class="mt-4 border-top pt-1 small">{{ __('Cashier') }}</div>
    <div class="mt-4 border-top pt-1 small">{{ __('Manager') }}</div>
</x-layouts::print>
