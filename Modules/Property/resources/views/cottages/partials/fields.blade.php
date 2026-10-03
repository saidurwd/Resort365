{{-- A cottage's own fields (create and edit). --}}
<div class="row">
    <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$cottage?->code" required :help="__('E.g. C01.')" /></div>
    <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="$cottage?->name" required /></div>
    <div class="col-md-4"><x-form.select name="cottage_type_id" :label="__('Cottage type')" :options="$cottageTypes" :value="$cottage?->cottage_type_id" required :placeholder="__('Choose…')" /></div>
    <div class="col-md-4"><x-form.input name="zone" :label="__('Zone')" :value="$cottage?->zone" :help="__('E.g. Beachfront, Garden.')" /></div>
    <div class="col-md-4"><x-form.select name="booking_mode" :label="__('Booking mode')" :options="\Modules\Property\Enums\BookingMode::options()" :value="$cottage?->booking_mode->value ?? 'both'" required :search="false" /></div>
    <div class="col-md-4"><x-form.select name="status" :label="__('Status')" :options="\Modules\Property\Enums\CottageStatus::options()" :value="$cottage?->status->value ?? 'active'" required :search="false" /></div>
    <div class="col-md-4"><x-form.input name="max_occupancy_override" type="number" min="1" :label="__('Max guests (override)')" :value="$cottage?->max_occupancy_override" :help="__('Leave empty to add up the rooms.')" /></div>
    <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$cottage?->sort_order ?? 0" /></div>
    <div class="col-12">
        <x-form.field name="description" :label="__('Description')">
            <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $cottage?->description) }}</textarea>
        </x-form.field>
    </div>
</div>
