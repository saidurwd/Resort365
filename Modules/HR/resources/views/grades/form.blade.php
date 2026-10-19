@php($title = $record ? $record->name : __('New grade'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Setup') => null, __('Grades') => route('hr.grades.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('hr.grades.update', $record) : route('hr.grades.store') }}">
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card>
            <div class="row">
                <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required /></div>
                <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                <div class="col-md-3"><x-form.input name="level" type="number" min="1" max="99" :label="__('Level')" :value="$record?->level ?? 1" :help="__('Higher is more senior.')" required /></div>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
            </div>
        </x-card>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('hr.grades.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
