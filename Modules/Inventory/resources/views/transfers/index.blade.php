<x-layouts::app :title="__('Stock transfers')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Transfers') => null]">
    <x-slot:actions>
        @if ($canManage)<a href="{{ route('inventory.transfers.create') }}" class="btn btn-primary" data-new-transfer><i class="bi bi-plus-lg"></i> {{ __('New transfer') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-transfers>
                <thead><tr><th class="ps-3">{{ __('Transfer') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="text-end">{{ __('Items') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr data-transfer="{{ $transfer->transfer_no }}">
                            <td class="ps-3">{{ $transfer->transfer_no }}</td><td>{{ $transfer->from->name }}</td><td>{{ $transfer->to->name }}</td><td class="text-end">{{ $transfer->lines_count }}</td>
                            <td>{{ $transfer->dispatched_at->inPropertyTime()->format('d M Y H:i') }}</td><td><x-status-badge :status="$transfer->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('inventory.transfers.show', $transfer) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No transfers yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
