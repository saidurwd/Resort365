<x-layouts::app :title="__('Wastage')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Wastage') => null]">
    <x-slot:actions>
        @if ($canRecord)<a href="{{ route('restaurant.wastage.create') }}" class="btn btn-primary" data-new-wastage><i class="bi bi-plus-lg"></i> {{ __('Write off food') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-wastage>
            <thead><tr><th class="ps-3">{{ __('Entry') }}</th><th>{{ __('Date') }}</th><th>{{ __('Store') }}</th><th>{{ __('Reason') }}</th><th class="text-end">{{ __('Cost') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr data-entry="{{ $entry->wastage_no }}"><td class="ps-3">{{ $entry->wastage_no }}</td><td>{{ $entry->wasted_on->format('d M Y') }}</td><td>{{ $stores[$entry->store_id] ?? '—' }}</td><td><x-status-badge :status="$entry->reason" /></td>
                        <td class="text-end font-monospace">{{ number_format((float) $entry->total_cost, 2) }}</td><td class="text-end pe-3"><a href="{{ route('restaurant.wastage.show', $entry) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td></tr>
                @empty
                    <tr><td colspan="6"><x-empty-state :title="__('Nothing written off yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
