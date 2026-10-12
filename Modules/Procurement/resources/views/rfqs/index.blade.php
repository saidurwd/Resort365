<x-layouts::app :title="__('Requests for quotation')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('RFQs') => null]">
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-rfqs>
                <thead><tr><th class="ps-3">{{ __('RFQ') }}</th><th>{{ __('Requisition') }}</th><th class="text-end">{{ __('Vendors') }}</th><th class="text-end">{{ __('Quotations') }}</th><th>{{ __('Reply by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rfqs as $rfq)
                        <tr data-rfq="{{ $rfq->rfq_no }}">
                            <td class="ps-3">{{ $rfq->rfq_no }}</td><td>{{ $rfq->requisition->requisition_no }}</td><td class="text-end">{{ $rfq->vendors_count }}</td><td class="text-end">{{ $rfq->quotations_count }}</td>
                            <td>{{ $rfq->due_on?->format('d M Y') ?? '—' }}</td><td><x-status-badge :status="$rfq->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.rfqs.show', $rfq) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No RFQs yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
