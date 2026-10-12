<x-layouts::app :title="__('Purchase orders')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Purchase orders') => null]">
    <x-slot:actions>
        @if ($canCreate)<a href="{{ route('procurement.orders.create') }}" class="btn btn-primary" data-new-order><i class="bi bi-plus-lg"></i> {{ __('New order') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-orders>
                <thead><tr><th class="ps-3">{{ __('Order') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Date') }}</th><th>{{ __('Expected') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr data-order="{{ $order->order_no }}">
                            <td class="ps-3">{{ $order->order_no }}</td><td>{{ $order->vendor->name }}</td><td>{{ $order->ordered_on->format('d M Y') }}</td><td>{{ $order->expected_on?->format('d M Y') ?? '—' }}</td>
                            <td class="text-end font-monospace">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td><td><x-status-badge :status="$order->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.orders.show', $order) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No purchase orders yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
