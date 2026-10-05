@php
    $money = fn ($amount): string => number_format((float) $amount, 2);
    $quantity = fn (string $quantity): string => rtrim(rtrim($quantity, '0'), '.');
@endphp
{{-- A restaurant bill (pre-check) or receipt on 80 mm paper (ARCHITECTURE §5.10.7). ?auto=1 prints it on load (POS print frame). --}}
<x-layouts::print :title="$bill->bill_no" paper="receipt">
    <div class="text-center mb-2" data-bill-document="{{ $receipt ? 'receipt' : 'bill' }}" data-copy="{{ $copy ? 'copy' : 'original' }}">
        <div class="fw-bold">{{ $property?->name }}</div>
        <div>{{ $bill->outlet->name }}</div>
        @if ($bill->outlet->receipt_header)<div class="small">{{ $bill->outlet->receipt_header }}</div>@endif
        <div class="fw-bold fs-5 mt-1">{{ $receipt ? __('RECEIPT') : __('BILL') }}</div>
        @if ($copy)<div class="fw-bold">*** {{ __('COPY') }} ***</div>@endif
        @if ($bill->status === \Modules\Restaurant\Enums\BillStatus::Voided)<div class="fw-bold">*** {{ __('VOIDED') }} ***</div>@endif
    </div>
    <table class="table table-sm mb-2">
        <tr><td>{{ __('Bill') }} {{ $bill->bill_no }}</td><td class="text-end">{{ $bill->split_label }}</td></tr>
        <tr><td>{{ $bill->order->table ? __('Table :number', ['number' => $bill->order->table->number]) : $bill->order->order_type->label() }}</td><td class="text-end">{{ $bill->order->order_no }}</td></tr>
        <tr><td>{{ $bill->business_date->format('d M Y') }}</td><td class="text-end">{{ ($receipt ? $bill->settled_at : $bill->printed_at)?->format('H:i') }}</td></tr>
    </table>
    <table class="table table-sm mb-2">
        @foreach ($bill->lines as $line)
            <tr data-bill-line><td>{{ $quantity((string) $line->quantity) }} × {{ $line->name_snapshot }}</td><td class="text-end">{{ $money($line->amount) }}</td></tr>
            @if ((float) $line->discount > 0)
                <tr><td class="ps-3 small">{{ __('Discount') }}</td><td class="text-end small">−{{ $money($line->discount) }}</td></tr>
            @endif
        @endforeach
    </table>
    <table class="table table-sm mb-2">
        <tr><td>{{ __('Subtotal') }}</td><td class="text-end">{{ $money($bill->subtotal) }}</td></tr>
        @if ((float) $bill->discount_total > 0)<tr><td>{{ __('Discounts') }}</td><td class="text-end">−{{ $money($bill->discount_total) }}</td></tr>@endif
        @foreach ($bill->tax_breakdown ?? [] as $tax)
            <tr><td>{{ $tax['name'] }} {{ $tax['rate'] !== '' ? rtrim(rtrim($tax['rate'], '0'), '.').'%' : '' }}{{ $inclusive ? ' ('.__('included').')' : '' }}</td><td class="text-end">{{ $money($tax['amount']) }}</td></tr>
        @endforeach
        <tr class="fw-bold fs-5"><td>{{ __('Total') }}</td><td class="text-end" data-bill-total>{{ $money($bill->grand_total) }}</td></tr>
    </table>
    @if ($receipt)
        <table class="table table-sm mb-2">
            @foreach ($bill->payments as $payment)
                <tr><td>{{ $payment->method->label() }}{{ $payment->reference ? ' · '.$payment->reference : '' }}{{ $payment->refund_of_id ? ' ('.__('refund').')' : '' }}</td><td class="text-end">{{ $money($payment->amount) }}</td></tr>
                @if ((float) $payment->tip != 0)<tr><td class="ps-3">{{ __('Tip') }}</td><td class="text-end">{{ $money($payment->tip) }}</td></tr>@endif
                @if ((float) $payment->change_given > 0)<tr><td class="ps-3">{{ __('Cash given') }} {{ $money($payment->tendered) }}</td><td class="text-end">{{ __('Change') }} {{ $money($payment->change_given) }}</td></tr>@endif
            @endforeach
        </table>
        @if ($bill->is_complimentary)<p class="text-center mb-2">{{ __('Complimentary') }}: {{ $bill->comp_reason?->label() }}</p>@endif
    @else
        <p class="text-center small mb-2">{{ __('Tips are welcome and go to the team.') }}</p>
    @endif
    @if ($bill->outlet->receipt_footer)<p class="text-center small mb-0">{{ $bill->outlet->receipt_footer }}</p>@endif
    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-layouts::print>
