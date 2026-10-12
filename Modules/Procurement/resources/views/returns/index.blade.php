<x-layouts::app :title="__('Purchase returns')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Returns') => null]">
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-returns>
                <thead><tr><th class="ps-3">{{ __('Return') }}</th><th>{{ __('Debit note') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Value') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($returns as $return)
                        <tr data-return="{{ $return->return_no }}">
                            <td class="ps-3">{{ $return->return_no }}</td><td>{{ $return->debit_note_no }}</td><td>{{ $return->vendor->name }}</td><td>{{ $return->receipt->receipt_no }}</td><td>{{ $return->returned_on->format('d M Y') }}</td>
                            <td class="text-end font-monospace">{{ number_format((float) $return->total_value, 2) }}</td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.returns.show', $return) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No returns yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
