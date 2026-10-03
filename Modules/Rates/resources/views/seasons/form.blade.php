@php
    $title = $season ? $season->name : __('New season');
    $periods = old('periods', $season?->periods->map(fn ($p) => ['start_date' => $p->start_date->toDateString(), 'end_date' => $p->end_date->toDateString()])->all() ?? [['start_date' => '', 'end_date' => '']]);
@endphp

<x-layouts::app :title="$title" :breadcrumbs="[__('Seasons') => route('rates.seasons.index'), $title => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ $season ? route('rates.seasons.update', $season) : route('rates.seasons.store') }}">
                @csrf
                @if ($season)
                    @method('PUT')
                @endif
                <x-card :title="__('Season')" icon="bi-calendar-range">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$season?->name" required :help="__('E.g. Peak, Shoulder, Eid.')" /></div>
                        <div class="col-md-3"><x-form.input name="priority" type="number" min="1" :label="__('Priority')" :value="$season?->priority ?? 10" required :help="__('Higher wins.')" /></div>
                        <div class="col-md-3"><x-form.select name="color" :label="__('Colour')" :options="\Modules\Rates\Enums\SeasonColor::options()" :value="$season?->color->value ?? 'primary'" required :search="false" /></div>
                    </div>

                    <label class="form-label">{{ __('Periods') }}<span class="required-marker" aria-hidden="true">*</span></label>
                    <div x-data="{ periods: @js(array_values($periods)) }" data-season-periods>
                        <template x-for="(period, index) in periods" :key="index">
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col"><input type="date" class="form-control" :name="`periods[${index}][start_date]`" x-model="period.start_date" aria-label="{{ __('From') }}" required></div>
                                <div class="col-auto">–</div>
                                <div class="col"><input type="date" class="form-control" :name="`periods[${index}][end_date]`" x-model="period.end_date" aria-label="{{ __('To') }}" required></div>
                                <div class="col-auto">
                                    <button type="button" class="btn btn-outline-danger" @click="periods.splice(index, 1)" :disabled="periods.length === 1" aria-label="{{ __('Remove period') }}"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        </template>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="periods.push({ start_date: '', end_date: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add period') }}</button>
                    </div>
                    @foreach ($errors->get('periods*') as $messages)
                        @foreach ((array) $messages as $message)
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @endforeach
                    @endforeach

                    <div class="form-check form-switch mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $season?->is_active ?? true))>
                        <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('rates.seasons.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save season') }}</button>
                </div>
            </form>
        </div>
        @if ($season)
            <div class="col-xl-5"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
