<x-layouts::app :title="__('Food costing')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Food costing') => null]">
    <x-card>
        <form method="GET" class="row g-2 align-items-end" data-costing-filter>
            <div class="col-md-4"><x-form.select name="outlet" :label="__('Outlet')" :options="$outlets" :value="$outletId" :placeholder="__('All outlets')" /></div>
            <div class="col-md-2 mb-3"><button type="submit" class="btn btn-outline-secondary w-100">{{ __('Filter') }}</button></div>
            <div class="col-md-6 mb-3 small text-body-secondary">{{ __('Target food cost: :percent%. The suggested price is the plate cost divided by it.', ['percent' => rtrim(rtrim($target, '0'), '.')]) }}</div>
        </form>
    </x-card>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-costing>
                <thead><tr><th class="ps-3">{{ __('Dish') }}</th><th>{{ __('Outlet') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('Plate cost') }}</th><th class="text-end">{{ __('Food cost %') }}</th><th class="text-end">{{ __('Suggested price') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($over = $row['percent'] !== null && bccomp($row['percent'], $target, 2) > 0)
                        <tr data-dish="{{ $row['item']->code }}" data-outlet="{{ $row['outlet']->code }}">
                            <td class="ps-3">{{ $row['item']->translated('name') }}@if ($row['variant']) <span class="text-body-secondary">({{ $row['variant'] }})</span>@endif <span class="small text-body-secondary">{{ $row['item']->code }}</span></td>
                            <td>{{ $row['outlet']->name }}</td><td class="text-end font-monospace">{{ number_format((float) $row['price'], 2) }}</td>
                            @if ($row['has_recipe'])
                                <td class="text-end font-monospace" data-cost>{{ number_format((float) $row['cost'], 2) }}</td>
                                <td class="text-end font-monospace {{ $over ? 'text-danger fw-semibold' : '' }}" data-percent>{{ $row['percent'] !== null ? $row['percent'].'%' : '—' }}</td>
                                <td class="text-end font-monospace">{{ $row['suggested'] !== null ? number_format((float) $row['suggested'], 2) : '—' }}</td>
                            @else
                                <td colspan="3" class="text-end small text-body-secondary">{{ __('No recipe') }}</td>
                            @endif
                            <td class="text-end pe-3"><a href="{{ route('restaurant.recipes.edit', $row['item']) }}" class="btn btn-sm btn-outline-secondary">{{ __('Recipe') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No priced dishes yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
