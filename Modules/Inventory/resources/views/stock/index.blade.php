<x-layouts::app :title="__('Stock on hand')" :subtitle="$store?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stock on hand') => null]">
    <x-slot:actions>
        @if ($canAdjust)
            <a href="{{ route('inventory.openings.create') }}" class="btn btn-outline-secondary" data-openings><i class="bi bi-flag"></i> {{ __('Opening balances') }}</a>
            <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-outline-primary" data-adjust><i class="bi bi-sliders"></i> {{ __('Adjust stock') }}</a>
        @endif
    </x-slot:actions>

    <form method="GET" action="{{ route('inventory.stock.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-stock-filter>
        <x-form.select name="store" :label="__('Store')" :options="$stores" :value="$store?->id" :search="false" wrapper-class="mb-0" />
        <x-form.select name="category" :label="__('Category')" :options="$categories" :value="request('category')" :placeholder="__('All categories')" wrapper-class="mb-0" />
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="low" value="1" id="f-low" @checked(request()->boolean('low'))><label class="form-check-label" for="f-low">{{ __('Low stock only') }}</label></div>
        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="zero" value="1" id="f-zero" @checked(request()->boolean('zero'))><label class="form-check-label" for="f-zero">{{ __('Include empty') }}</label></div>
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>

    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-stock>
                <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Category') }}</th><th class="text-end">{{ __('On hand') }}</th><th class="text-end">{{ __('Average cost') }}</th><th class="text-end">{{ __('Value') }}</th><th class="text-end pe-3">{{ __('Reorder at') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($balance = $row['balance'])
                        <tr data-stock-item="{{ $balance->item->sku }}" class="{{ $row['low'] ? 'table-warning' : '' }}">
                            <td class="ps-3">{{ $balance->item->name }} <span class="small text-body-secondary">{{ $balance->item->sku }}</span> @if ($row['low'])<span class="badge text-bg-warning" data-low>{{ __('low') }}</span>@endif</td>
                            <td class="small">{{ $balance->item->category->name }}</td>
                            <td class="text-end font-monospace">{{ rtrim(rtrim((string) $balance->quantity, '0'), '.') ?: '0' }} {{ $balance->item->baseUnit->code }}</td>
                            <td class="text-end font-monospace">{{ number_format((float) $balance->avg_cost, 4) }}</td>
                            <td class="text-end font-monospace">{{ number_format((float) $balance->value(), 2) }}</td>
                            <td class="text-end pe-3 font-monospace">{{ (float) $row['level'] > 0 ? rtrim(rtrim($row['level'], '0'), '.') : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state :title="__('Nothing in stock here')" :message="__('Record opening balances or receive goods.')" /></td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot><tr class="fw-bold"><td class="ps-3" colspan="4">{{ __('Total value') }}</td><td class="text-end font-monospace" data-total-value>{{ number_format((float) $total, 2) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>
</x-layouts::app>
