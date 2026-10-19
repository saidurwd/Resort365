@php($title = $record ? $record->name : __('New holiday'))

<x-layouts::app :title="$title" :subtitle="$subtitle" :breadcrumbs="[__('HR') => null, __('Holidays') => route('hr.holidays.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('hr.holidays.update', $record) : route('hr.holidays.store') }}" data-holiday-form>
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card>
            <div class="row">
                <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                <div class="col-md-4"><x-form.date name="holiday_date" :label="__('Date')" :value="$record?->holiday_date->toDateString()" required /></div>
            </div>
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="is_paid" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_paid" id="field-is_paid" value="1" @checked(old('is_paid', $record?->is_paid ?? true))>
                <label class="form-check-label" for="field-is_paid">{{ __('Paid holiday') }}</label>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="repeats_yearly" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="repeats_yearly" id="field-repeats_yearly" value="1" @checked(old('repeats_yearly', $record?->repeats_yearly ?? false))>
                <label class="form-check-label" for="field-repeats_yearly">{{ __('Falls on the same date every year') }}</label>
            </div>
        </x-card>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('hr.holidays.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
