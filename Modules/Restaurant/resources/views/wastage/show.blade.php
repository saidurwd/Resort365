@php($qty = fn (?string $amount): string => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0')
<x-layouts::app :title="$entry->wastage_no" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Wastage') => route('restaurant.wastage.index'), $entry->wastage_no => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Written off')" icon="bi-trash3" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-wastage-lines>
                    <thead><tr><th class="ps-3">{{ __('Ingredient') }}</th><th class="text-end">{{ __('Quantity') }}</th><th class="text-end">{{ __('Unit cost') }}</th><th class="text-end pe-3">{{ __('Cost') }}</th></tr></thead>
                    <tbody>
                        @foreach ($entry->lines as $line)
                            <tr data-line="{{ $items[$line->item_id]->sku ?? $line->item_id }}"><td class="ps-3">{{ $items[$line->item_id]->name ?? '#'.$line->item_id }}</td><td class="text-end font-monospace">{{ $qty((string) $line->quantity) }} {{ $items[$line->item_id]->baseUnit ?? '' }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $line->unit_cost, 4) }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) $line->total_cost, 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th class="ps-3" colspan="3">{{ __('Total') }}</th><th class="text-end font-monospace pe-3" data-total>{{ number_format((float) $entry->total_cost, 2) }}</th></tr></tfoot>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Entry')" icon="bi-info-circle">
                <dl class="row mb-0"><dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $entry->wasted_on->format('d M Y') }}</dd><dt class="col-5">{{ __('Store') }}</dt><dd class="col-7">{{ $store ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Reason') }}</dt><dd class="col-7"><x-status-badge :status="$entry->reason" /></dd>@if ($entry->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $entry->notes }}</dd>@endif</dl>
            </x-card>
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
