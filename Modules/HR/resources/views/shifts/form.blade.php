@php($title = $record ? $record->name : __('New shift'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Setup') => null, __('Shifts') => route('hr.shifts.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('hr.shifts.update', $record) : route('hr.shifts.store') }}" data-shift-form>
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card>
            <div class="row">
                <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required /></div>
                <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                <div class="col-md-3"><x-form.select name="color" :label="__('Colour')" :options="['primary' => __('Blue'), 'success' => __('Green'), 'warning' => __('Yellow'), 'danger' => __('Red'), 'info' => __('Light blue'), 'secondary' => __('Grey')]" :value="$record?->color ?? 'primary'" :search="false" /></div>
                <div class="col-md-3"><x-form.input name="starts_at" type="time" :label="__('Starts')" :value="$record ? substr($record->starts_at, 0, 5) : '09:00'" required /></div>
                <div class="col-md-3"><x-form.input name="ends_at" type="time" :label="__('Ends')" :value="$record ? substr($record->ends_at, 0, 5) : '17:00'" required :help="__('An end at or before the start runs into the next day.')" /></div>
                <div class="col-md-2"><x-form.input name="break_minutes" type="number" min="0" :label="__('Unpaid break (min)')" :value="$record?->break_minutes ?? 30" /></div>
                <div class="col-md-2"><x-form.input name="grace_minutes" type="number" min="0" :label="__('Grace (min)')" :value="$record?->grace_minutes ?? 10" :help="__('Late or early leave inside this is ignored.')" /></div>
                <div class="col-md-2"><x-form.input name="overtime_after_minutes" type="number" min="0" :label="__('Overtime after (min)')" :value="$record?->overtime_after_minutes ?? 15" /></div>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
            </div>
        </x-card>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('hr.shifts.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
