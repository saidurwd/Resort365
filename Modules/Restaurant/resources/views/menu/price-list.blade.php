@php
    $dayNames = ['mon' => __('Mon'), 'tue' => __('Tue'), 'wed' => __('Wed'), 'thu' => __('Thu'), 'fri' => __('Fri'), 'sat' => __('Sat'), 'sun' => __('Sun')];
    $scheduleNames = $schedules->pluck('name', 'id')->all();
@endphp
<x-layouts::app :title="__('Price list · :outlet', ['outlet' => $outlet->name])"
    :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Outlets') => route('restaurant.outlets.index'), $outlet->name => route('restaurant.outlets.show', $outlet), __('Price list') => null]">
    <ul class="nav nav-tabs mb-3" role="tablist" data-hash-tabs>
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-prices" data-tab-hash="prices"><i class="bi bi-tags"></i> {{ __('Prices') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-schedules" data-tab-hash="schedules"><i class="bi bi-clock"></i> {{ __('Schedules') }} <span class="badge text-bg-secondary">{{ $schedules->count() }}</span></button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-prices" role="tabpanel"
            x-data="priceList(@js(['rows' => $rows, 'soldOutUrl' => route('restaurant.outlets.prices.sold-out', [$outlet, '__ROW__']), 'canPrice' => $canPrice, 'canSoldOut' => $canSoldOut]))">
            <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                <div><label class="form-label" for="price-filter">{{ __('Find') }}</label><input type="search" id="price-filter" class="form-control" x-model="filter" placeholder="{{ __('Code, name or category') }}"></div>
                <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="only-on-sale" x-model="onlyOnSale"><label class="form-check-label" for="only-on-sale">{{ __('Only items sold here') }}</label></div>
                @if ($canPrice)
                    <div class="ms-auto d-flex gap-2 align-items-end">
                        <div>
                            <label class="form-label" for="bulk-station">{{ __('Station for the rows shown') }}</label>
                            <select id="bulk-station" class="form-select" x-model="bulkStation"><option value="">{{ __('None') }}</option>@foreach ($stations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                        </div>
                        <button type="button" class="btn btn-outline-primary" @click="applyStation()">{{ __('Apply') }}</button>
                    </div>
                @endif
            </div>
            <p class="text-danger small" x-show="message" x-text="message"></p>

            <form method="POST" action="{{ route('restaurant.outlets.prices.update', $outlet) }}" data-price-list>
                @csrf @method('PUT')
                <input type="hidden" name="rows_json" :value="json">
                <x-card body-class="p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 price-list">
                            <thead><tr>
                                <th class="ps-3">{{ __('Sold here') }}</th><th>{{ __('Item') }}</th><th>{{ __('Price') }}</th><th>{{ __('Station') }}</th>
                                @if ($schedules->isNotEmpty())<th>{{ __('Schedules') }}</th>@endif
                                <th>{{ __('Meal plan') }}</th><th class="pe-3">{{ __('Sold out (86)') }}</th>
                            </tr></thead>
                            <tbody>
                                <template x-for="row in shown" :key="row.item_id + ':' + row.variant_id">
                                    <tr :class="{ 'text-body-secondary': ! row.on_sale }" :data-price-row="row.code + (row.variant ? '/' + row.variant : '')">
                                        <td class="ps-3"><input type="checkbox" class="form-check-input" x-model="row.on_sale" :disabled="! canPrice" :aria-label="'{{ __('Sold here') }}: ' + row.name"></td>
                                        <td>
                                            <span class="fw-semibold" x-text="row.code"></span> <span x-text="row.name"></span>
                                            <span class="badge text-bg-secondary" x-show="row.variant" x-text="row.variant"></span>
                                            <div class="small text-body-secondary" x-text="row.category"></div>
                                        </td>
                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm price-input" x-model="row.price" :disabled="! row.on_sale || ! canPrice" aria-label="{{ __('Price') }}"></td>
                                        <td>
                                            <select class="form-select form-select-sm" x-model="row.station_id" :disabled="! row.on_sale || ! canPrice" aria-label="{{ __('Station') }}">
                                                <option value="">{{ __('None') }}</option>
                                                @foreach ($stations as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                            </select>
                                        </td>
                                        @if ($schedules->isNotEmpty())
                                            <td class="small text-nowrap">
                                                @foreach ($scheduleNames as $id => $name)
                                                    <label class="me-2"><input type="checkbox" class="form-check-input" :checked="row.schedule_ids.includes({{ $id }})" @change="toggleSchedule(row, {{ $id }})" :disabled="! row.on_sale || ! canPrice"> {{ $name }}</label>
                                                @endforeach
                                            </td>
                                        @endif
                                        <td><input type="checkbox" class="form-check-input" x-model="row.is_package_eligible" :disabled="! row.on_sale || ! canPrice" aria-label="{{ __('Covered by meal plans') }}"></td>
                                        <td class="pe-3">
                                            <button type="button" class="btn btn-sm" :class="row.is_available ? 'btn-outline-secondary' : 'btn-danger'" @click="soldOut(row)"
                                                :disabled="! row.on_sale || ! canSoldOut || (! canPrice && ! row.row_id)" data-sold-out
                                                x-text="row.is_available ? '{{ __('On sale') }}' : '{{ __('Sold out') }}'"></button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-card>
                @if ($canPrice)
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save price list') }}</button>
                    <span class="small text-body-secondary ms-2">{{ __('Prices are :tax.', ['tax' => $outlet->prices_include_tax ? __('including tax') : __('before tax')]) }}</span>
                @endif
            </form>

            @if ($canPrice && $otherOutlets)
                <x-card :title="__('Copy prices from another outlet')" icon="bi-files" class="mt-4">
                    <form method="POST" action="{{ route('restaurant.outlets.prices.copy', $outlet) }}" class="d-flex flex-wrap gap-2 align-items-end" data-copy-prices>
                        @csrf
                        <x-form.select name="from_outlet_id" :label="__('From')" :options="$otherOutlets" :search="false" wrapper-class="mb-0" required />
                        <x-form.input name="percent" type="number" step="0.01" :label="__('Change prices by %')" value="0" wrapper-class="mb-0" />
                        <div class="form-check mb-2"><input type="hidden" name="round_whole" value="0"><input type="checkbox" class="form-check-input" name="round_whole" value="1" id="round-whole" checked><label class="form-check-label" for="round-whole">{{ __('Round to whole taka') }}</label></div>
                        <div class="form-check mb-2"><input type="hidden" name="overwrite" value="0"><input type="checkbox" class="form-check-input" name="overwrite" value="1" id="overwrite"><label class="form-check-label" for="overwrite">{{ __('Replace prices already set here') }}</label></div>
                        <button type="submit" class="btn btn-outline-primary">{{ __('Copy') }}</button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="tab-pane fade" id="tab-schedules" role="tabpanel">
            <p class="text-body-secondary">{{ __('Items limited to schedules are sold only while one of them runs, at its price change (e.g. −20 for happy hour). Items with no schedule are sold whenever the outlet is open.') }}</p>
            @foreach ($schedules->push(null) as $schedule)
                @continue(! $canPrice && $schedule === null)
                <x-card :title="$schedule?->name ?? __('New schedule')" :icon="$schedule ? 'bi-clock' : 'bi-plus-lg'">
                    <form method="POST" action="{{ $schedule ? route('restaurant.outlets.schedules.update', [$outlet, $schedule]) : route('restaurant.outlets.schedules.store', $outlet) }}" class="d-flex flex-wrap gap-3 align-items-end" data-schedule-form>
                        @csrf
                        @if ($schedule) @method('PUT') @endif
                        <fieldset class="d-flex flex-wrap gap-3 align-items-end" @disabled(! $canPrice)>
                            <x-form.input name="name" :id="'schedule-name-'.($schedule->id ?? 'new')" :label="__('Name')" :value="$schedule?->name" wrapper-class="mb-0" required />
                            <div>
                                <div class="form-label">{{ __('Days') }}</div>
                                @foreach ($dayNames as $day => $name)
                                    <label class="me-2"><input type="checkbox" class="form-check-input" name="days_of_week[]" value="{{ $day }}" @checked(in_array($day, $schedule?->days_of_week ?? array_keys($dayNames), true))> {{ $name }}</label>
                                @endforeach
                            </div>
                            <x-form.input name="start_time" type="time" :id="'schedule-start-'.($schedule->id ?? 'new')" :label="__('From')" :value="$schedule ? substr($schedule->start_time, 0, 5) : ''" wrapper-class="mb-0" required />
                            <x-form.input name="end_time" type="time" :id="'schedule-end-'.($schedule->id ?? 'new')" :label="__('Until')" :value="$schedule ? substr($schedule->end_time, 0, 5) : ''" wrapper-class="mb-0" required />
                            <x-form.input name="price_adjustment_percent" type="number" step="0.01" :id="'schedule-adjust-'.($schedule->id ?? 'new')" :label="__('Price change %')" :value="$schedule?->price_adjustment_percent ?? '0'" wrapper-class="mb-0" />
                            <div class="form-check mb-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="schedule-active-{{ $schedule->id ?? 'new' }}" @checked($schedule?->is_active ?? true)><label class="form-check-label" for="schedule-active-{{ $schedule->id ?? 'new' }}">{{ __('Active') }}</label></div>
                            @if ($canPrice)<button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>@endif
                        </fieldset>
                    </form>
                </x-card>
            @endforeach
        </div>
    </div>
</x-layouts::app>
