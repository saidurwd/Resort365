<x-layouts::app :title="__('Cottages and rooms')" :breadcrumbs="[__('Setup checklist') => route('core.onboarding.index'), __('Cottages and rooms') => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ route('property.onboarding.rooms.store') }}">
                @csrf
                <x-card :title="__('How many rooms do you have?')" icon="bi-door-open">
                    <p class="text-body-secondary">{{ __('Start simple: all cottages the same. Rooms are numbered from 101. You can add other kinds of rooms, rename them and change the details in Setup afterwards.') }}</p>
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="cottage_count" type="number" min="1" max="100" :label="__('Number of cottages')" value="4" required /></div>
                        <div class="col-md-6"><x-form.input name="rooms_per_cottage" type="number" min="1" max="20" :label="__('Rooms in each cottage')" value="2" required /></div>
                        <div class="col-md-8"><x-form.input name="room_type_name" :label="__('Kind of room')" :value="__('Standard room')" required /></div>
                        <div class="col-md-4"><x-form.input name="max_occupancy" type="number" min="1" max="20" :label="__('Guests per room (most)')" value="3" required /></div>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.onboarding.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create the rooms') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
