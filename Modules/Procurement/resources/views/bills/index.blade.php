<x-layouts::app :title="__('Vendor bills')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendor bills') => null]">
    <x-slot:actions>
        @if ($canCreate)<a href="{{ route('procurement.bills.create') }}" class="btn btn-primary" data-new-bill><i class="bi bi-plus-lg"></i> {{ __('Service or expense bill') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-bills>
                <thead><tr><th class="ps-3">{{ __('Bill') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Their invoice') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($bills as $bill)
                        <tr data-bill="{{ $bill->bill_no }}">
                            <td class="ps-3">{{ $bill->bill_no }}</td><td>{{ $bill->vendor->name }}</td><td>{{ $bill->vendor_reference ?? '—' }}</td><td>{{ $bill->due_on->format('d M Y') }}</td>
                            <td class="text-end font-monospace">{{ number_format((float) $bill->total, 2) }}</td><td class="text-end font-monospace">{{ number_format((float) $bill->paid_amount, 2) }}</td>
                            <td><x-status-badge :status="$bill->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.bills.show', $bill) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state :title="__('No vendor bills yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
