@php
    $dayNames = [1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat'), 7 => __('Sun')];
    $unitOptions = ['*' => __('All types')] + collect($units)->mapWithKeys(fn ($unit) => [$unit->key() => $unit->name])->all();
    $from = $start->toDateString();
    $to = end($dates)->toDateString();
@endphp

<x-layouts::app :title="__('Rate grid')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Rate grid') => null]">
    <x-card>
        <form method="GET" action="{{ route('rates.grid') }}" class="row g-2 align-items-end" data-grid-filter>
            <div class="col-md-4">
                <label for="grid-plan" class="form-label">{{ __('Rate plan') }}</label>
                <select name="plan" id="grid-plan" class="form-select" x-data x-on:change="$el.form.requestSubmit()">
                    @foreach ($plans as $item)
                        <option value="{{ $item->id }}" @selected($plan?->is($item))>{{ $item->name }}@unless ($item->is_active) ({{ __('inactive') }})@endunless</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label for="grid-start" class="form-label">{{ __('From') }}</label>
                <input type="date" name="start" id="grid-start" value="{{ $from }}" class="form-control">
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
                @if ($plan)
                    <a class="btn btn-outline-secondary" href="{{ route('rates.grid', ['plan' => $plan->id, 'start' => $start->subDays(14)->toDateString()]) }}" aria-label="{{ __('Previous 14 days') }}"><i class="bi bi-chevron-left"></i></a>
                    <a class="btn btn-outline-secondary" href="{{ route('rates.grid', ['plan' => $plan->id, 'start' => $start->addDays(14)->toDateString()]) }}" aria-label="{{ __('Next 14 days') }}"><i class="bi bi-chevron-right"></i></a>
                    @can('viewRates', $plan)
                        <a class="btn btn-outline-secondary ms-auto" href="{{ route('rates.rate-plans.rates.edit', $plan) }}"><i class="bi bi-cash-stack"></i> {{ __('Rate sheet') }}</a>
                    @endcan
                @endif
            </div>
        </form>
    </x-card>

    @if (! $plan)
        <x-card><x-empty-state icon="bi-tags" :title="__('No rate plans yet')" :message="__('Create a rate plan and enter its rates first.')" /></x-card>
    @elseif ($units === [])
        <x-card><x-empty-state icon="bi-door-open" :title="__('No room or cottage types yet')" :message="__('Add them under Setup first.')" /></x-card>
    @else
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 rate-grid" data-rate-grid>
                    <thead>
                        <tr>
                            <th class="rate-grid-unit">{{ __('Type') }} <span class="small text-body-secondary fw-normal">({{ $currency }})</span></th>
                            @foreach ($dates as $date)
                                <th @class(['text-center', 'is-weekend' => in_array($date->dayOfWeekIso, $weekend, true)]) data-date="{{ $date->toDateString() }}">
                                    <div class="small text-body-secondary">{{ $dayNames[$date->dayOfWeekIso] }}</div>
                                    <div>{{ $date->format('d M') }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr data-unit="{{ $unit->key() }}">
                                <th class="rate-grid-unit fw-normal">
                                    <div class="fw-semibold">{{ $unit->name }}</div>
                                    <div class="small text-body-secondary">{{ $unit->kind->label() }}</div>
                                </th>
                                @foreach ($dates as $date)
                                    @php
                                        $night = $rates[$unit->key()][$date->toDateString()] ?? null;
                                        $limits = $restrictions[$unit->key()][$date->toDateString()] ?? null;
                                    @endphp
                                    <td @class(['text-end', 'is-weekend' => in_array($date->dayOfWeekIso, $weekend, true), 'season-mark-'.$night?->seasonColor => $night?->seasonColor, 'text-decoration-line-through text-body-secondary' => $limits?->stopSell])
                                        data-date="{{ $date->toDateString() }}" data-amount="{{ $night?->amount }}" data-source="{{ $night?->source->value }}"
                                        title="{{ $night ? $night->source->label().($night->seasonName ? ' · '.$night->seasonName : '') : __('No price') }}">
                                        @if ($night)
                                            <span @class(['fw-semibold text-danger' => $night->source === \Modules\Rates\Enums\RateSource::Override])>{{ number_format((float) $night->amount) }}</span>
                                        @else
                                            <span class="text-body-tertiary">—</span>
                                        @endif
                                        @if ($limits && ! $limits->isEmpty())
                                            <div class="small lh-1" data-restrictions>
                                                @if ($limits->stopSell)<span class="badge text-bg-danger">{{ __('STOP') }}</span>@endif
                                                @if ($limits->minStay)<span class="badge text-bg-warning">{{ __('Min :n', ['n' => $limits->minStay]) }}</span>@endif
                                                @if ($limits->maxStay)<span class="badge text-bg-info">{{ __('Max :n', ['n' => $limits->maxStay]) }}</span>@endif
                                                @if ($limits->closedToArrival)<span class="badge text-bg-secondary">{{ __('CTA') }}</span>@endif
                                                @if ($limits->closedToDeparture)<span class="badge text-bg-secondary">{{ __('CTD') }}</span>@endif
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="d-flex flex-wrap gap-3 small align-items-center">
                    <span><span class="text-danger fw-semibold">1,234</span> {{ __('date price') }}</span>
                    @foreach ($seasons as $season)
                        <span class="season-legend season-mark-{{ $season->color }}">{{ $season->name }}</span>
                    @endforeach
                    <span class="bg-body-secondary px-2 border">{{ __('weekend') }}</span>
                    <span><span class="badge text-bg-warning">{{ __('Min 2') }}</span> {{ __('minimum stay') }} · <span class="badge text-bg-secondary">{{ __('CTA') }}</span>/<span class="badge text-bg-secondary">{{ __('CTD') }}</span> {{ __('closed to arrival / departure') }}</span>
                </div>
            </x-slot:footer>
        </x-card>

        @can('manageRates', $plan)
            <div class="row">
                <div class="col-xl-6">
                    <x-card :title="__('Date price')" icon="bi-calendar-event">
                        <form method="POST" action="{{ route('rates.grid.overrides', $plan) }}" data-override-form>
                            @csrf
                            @include('rates::grid.partials.range', ['prefix' => 'ov', 'from' => $from, 'to' => $to, 'unitOptions' => $unitOptions, 'dayNames' => $dayNames])
                            <x-form.money name="amount" :label="__('Price per night')" :currency="$currency" />
                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="set" class="btn btn-primary">{{ __('Set price') }}</button>
                                <button type="submit" name="action" value="clear" class="btn btn-outline-danger">{{ __('Clear date prices') }}</button>
                            </div>
                        </form>
                    </x-card>
                </div>
                <div class="col-xl-6">
                    <x-card :title="__('Restrictions')" icon="bi-sign-stop">
                        <form method="POST" action="{{ route('rates.grid.restrictions', $plan) }}" data-restriction-form>
                            @csrf
                            @include('rates::grid.partials.range', ['prefix' => 'rs', 'from' => $from, 'to' => $to, 'unitOptions' => $unitOptions, 'dayNames' => $dayNames])
                            <div class="row">
                                <div class="col-6"><x-form.input name="min_stay" type="number" min="1" :label="__('Minimum stay (nights)')" /></div>
                                <div class="col-6"><x-form.input name="max_stay" type="number" min="1" :label="__('Maximum stay (nights)')" /></div>
                            </div>
                            @foreach (['closed_to_arrival' => __('Closed to arrival'), 'closed_to_departure' => __('Closed to departure'), 'stop_sell' => __('Stop sell'), 'all_plans' => __('Apply to all rate plans')] as $field => $label)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="rs-{{ $field }}" @checked(old($field))>
                                    <label class="form-check-label" for="rs-{{ $field }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" name="action" value="set" class="btn btn-primary">{{ __('Save restrictions') }}</button>
                                <button type="submit" name="action" value="clear" class="btn btn-outline-danger">{{ __('Clear restrictions') }}</button>
                            </div>
                        </form>
                    </x-card>
                </div>
            </div>
        @endcan
    @endif
</x-layouts::app>
