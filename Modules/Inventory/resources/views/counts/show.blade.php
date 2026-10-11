<x-layouts::app :title="$count->count_no" :subtitle="$count->store->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stock counts') => route('inventory.counts.index'), $count->count_no => null]">
    <x-slot:actions>
        <a href="{{ route('inventory.counts.sheet', $count) }}" class="btn btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ __('Count sheet') }}</a>
        @if ($open && $canManage)
            <form method="POST" action="{{ route('inventory.counts.complete', $count) }}" class="d-inline" data-confirm="{{ __('Complete the count and adjust the stock to what was counted?') }}">@csrf<button type="submit" class="btn btn-success" data-complete><i class="bi bi-check2-circle"></i> {{ __('Complete') }}</button></form>
            <form method="POST" action="{{ route('inventory.counts.cancel', $count) }}" class="d-inline" data-confirm="{{ __('Cancel this count?') }}" data-confirm-variant="danger">@csrf<button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Cancel') }}</button></form>
        @endif
    </x-slot:actions>
    <form method="POST" action="{{ route('inventory.counts.save', $count) }}">
        @csrf @method('PUT')
        <x-card :title="__('Items')" icon="bi-clipboard-data" body-class="p-0">
            <table class="table table-sm align-middle mb-0" data-count-lines>
                <thead><tr><th class="ps-3">{{ __('Item') }}</th>@unless ($count->blind && $open)<th class="text-end">{{ __('Expected') }}</th>@endunless<th class="text-end">{{ __('Counted') }}</th>@unless ($count->blind && $open)<th class="text-end">{{ __('Difference') }}</th><th class="text-end pe-3">{{ __('Value') }}</th>@endunless</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($line = $row['line'])
                        <tr data-line="{{ $line->item->sku }}">
                            <td class="ps-3">{{ $line->item->name }} <span class="small text-body-secondary">{{ $line->item->sku }} · {{ $line->item->baseUnit->code }}</span></td>
                            @unless ($count->blind && $open)<td class="text-end font-monospace">{{ rtrim(rtrim((string) $row['expected'], '0'), '.') ?: '0' }}</td>@endunless
                            <td class="text-end" style="width:9rem">
                                @if ($open && $canManage)<input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" name="entries[{{ $line->item_id }}]" value="{{ $line->counted_quantity !== null ? rtrim(rtrim((string) $line->counted_quantity, '0'), '.') : '' }}" aria-label="{{ __('Counted') }}">
                                @else<span class="font-monospace">{{ $line->counted_quantity !== null ? (rtrim(rtrim((string) $line->counted_quantity, '0'), '.') ?: '0') : '—' }}</span>@endif
                            </td>
                            @unless ($count->blind && $open)
                                <td class="text-end font-monospace {{ $row['variance'] !== null && bccomp($row['variance'], '0', 4) < 0 ? 'text-danger' : '' }}">{{ $row['variance'] !== null ? (rtrim(rtrim($row['variance'], '0'), '.') ?: '0') : '—' }}</td>
                                <td class="text-end font-monospace pe-3">{{ $row['value'] !== null ? number_format((float) $row['value'], 2) : '—' }}</td>
                            @endunless
                        </tr>
                    @endforeach
                </tbody>
                @unless ($count->blind && $open)<tfoot><tr><th class="ps-3" colspan="3">{{ __('Net difference') }}</th><th></th><th class="text-end font-monospace pe-3" data-net>{{ number_format((float) $net, 2) }}</th></tr></tfoot>@endunless
            </table>
        </x-card>
        @if ($open && $canManage)<button type="submit" class="btn btn-primary mb-3" data-save-counts><i class="bi bi-save"></i> {{ __('Save counts') }}</button>@endif
    </form>
    <x-audit-trail :entries="$trail" />
</x-layouts::app>
