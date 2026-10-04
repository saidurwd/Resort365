<x-layouts::app :title="$schedule ? __('Edit schedule') : __('New schedule')" :subtitle="$property->name"
    :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Preventive maintenance') => route('housekeeping.schedules.index'), ($schedule ? __('Edit') : __('New')) => null]">
    <div class="row"><div class="col-xl-7">
        <x-card>
            <form method="POST" action="{{ $schedule ? route('housekeeping.schedules.update', $schedule) : route('housekeeping.schedules.store') }}" data-schedule-form>
                @csrf
                @if ($schedule) @method('PUT') @endif
                <x-form.input name="title" :label="__('Task')" :value="old('title', $schedule?->title)" required />
                <div class="row">
                    <div class="col-md-6"><x-form.select name="category" :label="__('Category')" :options="$categories" :value="old('category', $schedule?->category->value)" :search="false" required /></div>
                    <div class="col-md-6"><x-form.input name="interval_days" type="number" min="1" :label="__('Every (days)')" :value="old('interval_days', $schedule?->interval_days ?? 90)" required /></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><x-form.select name="room_id" :label="__('Room')" :options="$rooms" :value="old('room_id', $schedule?->room_id)" :placeholder="__('Not one room')" /></div>
                    <div class="col-md-6"><x-form.input name="location" :label="__('Or where')" :value="old('location', $schedule?->location)" :help="__('E.g. all rooms, generator.')" /></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><x-form.date name="next_due_on" :label="__('Next due')" :value="old('next_due_on', $schedule?->next_due_on->toDateString() ?? $property->businessDate)" required /></div>
                    <div class="col-md-6"><x-form.select name="assigned_to" :label="__('Technician')" :options="$technicians" :value="old('assigned_to', $schedule?->assigned_to)" :placeholder="__('Nobody yet')" /></div>
                </div>
                <x-form.input name="notes" :label="__('Notes')" :value="old('notes', $schedule?->notes)" />
                <div class="form-check mb-3">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="form-check-input" name="is_active" value="1" id="field-is_active" @checked(old('is_active', $schedule?->is_active ?? true))>
                    <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::app>
