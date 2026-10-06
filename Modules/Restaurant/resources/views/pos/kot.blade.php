{{-- A kitchen order ticket on 80 mm paper (ARCHITECTURE §5.10.6). ?auto=1 prints it on load (POS print frame). --}}
<x-layouts::print :title="__('KOT #:no', ['no' => $kot->kot_no])" paper="receipt">
    <div class="text-center mb-2" data-kot-ticket="{{ $kot->kot_no }}">
        <div class="fw-bold">{{ $order->outlet->name }}</div>
        @if ($kot->type === \Modules\Restaurant\Enums\KotType::Void)
            <div class="fw-bold fs-5">*** {{ __('VOID') }} ***</div>
        @endif
        <div class="fw-bold fs-4">{{ __('KOT #:no', ['no' => $kot->kot_no]) }}</div>
        <div class="fw-bold">{{ $kot->station?->name ?? __('Kitchen') }}</div>
    </div>
    <table class="table table-sm mb-2">
        <tr><td>{{ $order->table ? __('Table :number', ['number' => $order->table->number]) : $order->order_type->label() }}</td><td class="text-end">{{ $order->order_no }}</td></tr>
        <tr><td>{{ $waiter }}</td><td class="text-end">{{ $kot->fired_at->inPropertyTime()->format('d M H:i') }}</td></tr>
        @if ($order->table)<tr><td colspan="2">{{ trans_choice(':count cover|:count covers', $order->covers) }}</td></tr>@endif
    </table>
    <table class="table table-sm mb-2">
        @foreach ($kot->lines as $kotLine)
            @php($line = $kotLine->line)
            <tr>
                <td class="fw-bold fs-5" data-kot-line>{{ $kotLine->quantity }} × {{ $line->name_snapshot }}@if ($line->variant_snapshot) ({{ $line->variant_snapshot }})@endif</td>
            </tr>
            @if ($line->modifiers || $line->notes || $line->seat_no || $line->void_reason)
                <tr><td class="ps-3">
                    @foreach ($line->modifiers ?? [] as $modifier)<div>+ {{ $modifier['name'] }}</div>@endforeach
                    @if ($line->notes)<div>** {{ $line->notes }}</div>@endif
                    @if ($line->seat_no)<div>{{ __('Seat :seat', ['seat' => $line->seat_no]) }}</div>@endif
                    @if ($kot->type === \Modules\Restaurant\Enums\KotType::Void && $line->void_reason)<div>{{ __('Reason') }}: {{ $line->void_reason->label() }}</div>@endif
                </td></tr>
            @endif
        @endforeach
    </table>
    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-layouts::print>
