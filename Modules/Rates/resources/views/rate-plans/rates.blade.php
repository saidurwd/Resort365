<x-layouts::app :title="__('Rates · :plan', ['plan' => $plan->name])" :subtitle="$season ? __('Season: :name', ['name' => $season->name]) : __('Base rates (outside seasons)')"
    :breadcrumbs="[__('Rate plans') => route('rates.rate-plans.index'), $plan->name => null]">
    <x-slot:actions>
        <a href="{{ route('rates.grid', ['plan' => $plan->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-grid-3x3"></i> {{ __('Rate grid') }}</a>
    </x-slot:actions>

    <ul class="nav nav-pills mb-3" data-rate-sheet-seasons>
        <li class="nav-item"><a @class(['nav-link', 'active' => ! $season]) href="{{ route('rates.rate-plans.rates.edit', $plan) }}">{{ __('Base rates') }}</a></li>
        @foreach ($seasons as $item)
            <li class="nav-item"><a @class(['nav-link', 'active' => $season?->is($item)]) href="{{ route('rates.rate-plans.rates.edit', ['rate_plan' => $plan, 'season' => $item->id]) }}">{{ $item->name }}</a></li>
        @endforeach
    </ul>

    @if ($units === [])
        <x-card><x-empty-state icon="bi-door-open" :title="__('No room or cottage types yet')" :message="__('Add them under Setup first.')" /></x-card>
    @else
        @can('manageRates', $plan)
            <form method="POST" action="{{ route('rates.rate-plans.rates.update', ['rate_plan' => $plan, 'season' => $season?->id]) }}">
                @csrf
                @method('PUT')
        @endcan
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" data-rate-sheet>
                    <thead>
                        <tr>
                            <th class="ps-3" rowspan="2">{{ __('Room or cottage type') }}</th>
                            <th colspan="3" class="text-center border-start">{{ __('Every day') }} ({{ $currency }})</th>
                            <th colspan="3" class="text-center border-start">{{ __('Weekend: :days', ['days' => $weekendLabel]) }} ({{ $currency }})</th>
                        </tr>
                        <tr class="small">
                            @foreach (['every', 'weekend'] as $set)
                                <th class="border-start">{{ __('Night') }}</th>
                                <th>{{ __('Extra adult') }}</th>
                                <th>{{ __('Extra child') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $unit->name }}</div>
                                    <div class="small text-body-secondary">{{ $unit->kind->label() }} · {{ __('max :count guests', ['count' => $unit->maxOccupancy]) }}</div>
                                </td>
                                @foreach (['every', 'weekend'] as $set)
                                    @php($rate = $values($unit->key(), $set))
                                    @foreach (['amount', 'extra_adult_amount', 'extra_child_amount'] as $field)
                                        @php($name = "rates[{$unit->key()}][{$set}][{$field}]")
                                        <td @class(['border-start' => $field === 'amount'])>
                                            <input type="text" inputmode="decimal" name="{{ $name }}" value="{{ old("rates.{$unit->key()}.{$set}.{$field}", $rate?->{$field}) }}"
                                                @class(['form-control form-control-sm text-end', 'is-invalid' => $errors->has("rates.{$unit->key()}.{$set}.{$field}")])
                                                aria-label="{{ $unit->name }} · {{ $set === 'every' ? __('Every day') : __('Weekend') }} · {{ $field }}"
                                                @cannot('manageRates', $plan) readonly @endcannot>
                                        </td>
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="small text-body-secondary">{{ __('Leave the weekend night empty to use the every-day rate on the weekend. An empty night removes the rate.') }}</div>
            </x-slot:footer>
        </x-card>
        @can('manageRates', $plan)
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <button type="submit" class="btn btn-primary">{{ __('Save rates') }}</button>
                </div>
            </form>
        @endcan
    @endif
</x-layouts::app>
