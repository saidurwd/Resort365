@php($title = $record ? $record->name : __('New designation'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Setup') => null, __('Designations') => route('hr.designations.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('hr.designations.update', $record) : route('hr.designations.store') }}">
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card>
            <x-form.input name="name" :label="__('Name')" :value="$record?->name" required />
            <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$record?->department_id" :placeholder="__('Any department')" />
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
            </div>
        </x-card>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('hr.designations.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
