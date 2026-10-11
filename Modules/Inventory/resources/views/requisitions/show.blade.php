<x-layouts::app :title="$requisition->requisition_no" :subtitle="$requisition->store->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Requisitions') => route('inventory.requisitions.index'), $requisition->requisition_no => null]">
    <x-slot:actions>
        @if ($canWithdraw)
            <form method="POST" action="{{ route('inventory.requisitions.withdraw', $requisition) }}" class="d-inline" data-confirm="{{ __('Withdraw this requisition?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-withdraw><i class="bi bi-x-circle"></i> {{ __('Withdraw') }}</button></form>
        @endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('inventory.requisitions.issue', $requisition) }}">
                @csrf
                <x-card :title="__('Items')" icon="bi-box-seam" body-class="p-0">
                    <table class="table table-sm align-middle mb-0" data-requisition-lines>
                        <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Requested') }}</th><th class="text-end">{{ __('Issued') }}</th><th class="text-end">{{ __('In store') }}</th>@if ($canIssue)<th class="text-end pe-3">{{ __('Issue now') }}</th>@endif</tr></thead>
                        <tbody>
                            @foreach ($requisition->lines as $line)
                                @php($left = bcsub((string) $line->quantity, (string) $line->issued_quantity, 4))
                                <tr data-line="{{ $line->item->sku }}">
                                    <td class="ps-3">{{ $line->item->name }} <span class="small text-body-secondary">{{ $line->item->sku }}</span></td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }} {{ $line->item->baseUnit->code }}</td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim((string) $line->issued_quantity, '0'), '.') ?: '0' }}</td>
                                    <td class="text-end font-monospace">{{ rtrim(rtrim((string) ($available[$line->item_id] ?? '0'), '0'), '.') ?: '0' }}</td>
                                    @if ($canIssue)<td class="text-end pe-3" style="width:9rem"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="quantities[{{ $line->item_id }}]" value="{{ bccomp($left, '0', 4) > 0 ? rtrim(rtrim($left, '0'), '.') : '' }}" aria-label="{{ __('Issue now') }}"></td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                @if ($canIssue)<button type="submit" class="btn btn-success mb-3" data-issue><i class="bi bi-box-arrow-right"></i> {{ __('Issue') }}</button>@endif
            </form>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Requisition')" icon="bi-clipboard-check">
                <dl class="row mb-0" data-requisition-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$requisition->status" /></dd>
                    <dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $department ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Estimated value') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $requisition->estimated_value, 2) }}</dd>
                    @if ($requisition->needed_on)<dt class="col-5">{{ __('Needed by') }}</dt><dd class="col-7">{{ $requisition->needed_on->format('d M Y') }}</dd>@endif
                    @if ($approval)<dt class="col-5">{{ __('Approval') }}</dt><dd class="col-7"><a href="{{ $approval->url }}">{{ $approval->status->label() }}@if ($approval->currentStep) · {{ $approval->currentStep }}@endif</a></dd>@endif
                    @if ($requisition->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $requisition->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
