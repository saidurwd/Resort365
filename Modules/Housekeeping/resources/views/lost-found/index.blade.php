<x-layouts::app :title="__('Lost & found')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Lost & found') => null]">
    <x-slot:actions>
        @if ($canManage)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#found-item"><i class="bi bi-plus-lg"></i> {{ __('Log a found item') }}</button>
        @endif
    </x-slot:actions>

    <x-card body-class="p-0">
        <x-datatable id="lost-found-table" :url="route('housekeeping.lost-found.data')" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('Nothing logged.')" />
    </x-card>

    @if ($canManage)
        <x-modal id="found-item" :title="__('Log a found item')" :show="$errors->hasAny(['found_on', 'found_at', 'description', 'room_id', 'stored_at'])">
            <form method="POST" action="{{ route('housekeeping.lost-found.store') }}" id="found-item-form" data-found-item>
                @csrf
                <x-form.input name="description" :label="__('Item')" required />
                <div class="row">
                    <div class="col-6"><x-form.date name="found_on" :label="__('Found on')" :value="old('found_on', now()->toDateString())" required /></div>
                    <div class="col-6"><x-form.select name="room_id" :label="__('Room')" :options="$rooms" :placeholder="__('Not in a room')" /></div>
                </div>
                <x-form.input name="found_at" :label="__('Where')" required />
                <x-form.input name="stored_at" :label="__('Kept at')" :value="old('stored_at', __('Front office safe'))" />
                <x-form.input name="notes" :label="__('Notes')" />
            </form>
            <x-slot:footer><button type="submit" form="found-item-form" class="btn btn-primary">{{ __('Log item') }}</button></x-slot:footer>
        </x-modal>

        <x-modal id="close-item" :title="__('Return or dispose of')">
            <form method="POST" id="close-item-form" data-close-item>
                @csrf
                <x-form.select name="outcome" :label="__('Outcome')" :options="['claimed' => __('Returned to its owner'), 'disposed' => __('Disposed of')]" :search="false" required />
                @if ($canFindGuests)
                    <x-form.select name="guest_id" :label="__('Guest')" :placeholder="__('Type a name, phone, email or ID…')" :tom-options="['remote' => route('guest.guests.search')]" />
                @endif
                <x-form.input name="claimed_by_name" :label="__('Or returned to (name)')" />
                <x-form.input name="notes" :label="__('Notes')" />
            </form>
            <x-slot:footer><button type="submit" form="close-item-form" class="btn btn-primary">{{ __('Close entry') }}</button></x-slot:footer>
        </x-modal>
    @endif
</x-layouts::app>
