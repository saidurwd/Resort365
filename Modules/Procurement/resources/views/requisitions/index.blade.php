<x-layouts::app :title="__('Purchase requisitions')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Requisitions') => null]">
    <x-slot:actions>
        @if ($canCreate)<a href="{{ route('procurement.requisitions.create') }}" class="btn btn-primary" data-new-requisition><i class="bi bi-plus-lg"></i> {{ __('New requisition') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-requisitions>
                <thead><tr><th class="ps-3">{{ __('Requisition') }}</th><th>{{ __('Department') }}</th><th>{{ __('Needed') }}</th><th>{{ __('Source') }}</th><th class="text-end">{{ __('Estimated value') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($requisitions as $requisition)
                        <tr data-requisition="{{ $requisition->requisition_no }}">
                            <td class="ps-3">{{ $requisition->requisition_no }}</td><td>{{ $departments[$requisition->department_id] ?? '—' }}</td><td>{{ $requisition->needed_on?->format('d M Y') ?? '—' }}</td>
                            <td><x-status-badge :status="$requisition->source" /></td><td class="text-end font-monospace">{{ number_format((float) $requisition->estimated_value, 2) }}</td>
                            <td><x-status-badge :status="$requisition->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.requisitions.show', $requisition) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No requisitions yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
