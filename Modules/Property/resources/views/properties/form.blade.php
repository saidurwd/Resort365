@php($title = $property ? $property->name : __('New property'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Properties') => route('property.properties.index'), $title => null]">
    <form method="POST" action="{{ $property ? route('property.properties.update', $property) : route('property.properties.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($property)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Property')" icon="bi-building">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$property?->code" required :help="__('Short, e.g. CXB. Used on documents.')" /></div>
                        <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="$property?->name" required /></div>
                        <div class="col-md-4"><x-form.select name="status" :label="__('Status')" :options="\Modules\Property\Enums\PropertyStatus::options()" :value="$property?->status->value ?? 'active'" required :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="legal_name" :label="__('Legal name')" :value="$property?->legal_name" /></div>
                        <div class="col-md-6"><x-form.input name="tax_registration_no" :label="__('Tax registration no.')" :value="$property?->tax_registration_no" /></div>
                        <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" :value="$property?->email" /></div>
                        <div class="col-md-6"><x-form.input name="phone" :label="__('Phone')" :value="$property?->phone" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Address')" icon="bi-geo-alt">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="address_line1" :label="__('Address line 1')" :value="$property?->address_line1" /></div>
                        <div class="col-md-6"><x-form.input name="address_line2" :label="__('Address line 2')" :value="$property?->address_line2" /></div>
                        <div class="col-md-4"><x-form.input name="city" :label="__('City')" :value="$property?->city" /></div>
                        <div class="col-md-4"><x-form.input name="state" :label="__('State / division')" :value="$property?->state" /></div>
                        <div class="col-md-4"><x-form.input name="postal_code" :label="__('Postal code')" :value="$property?->postal_code" /></div>
                        <div class="col-md-6"><x-form.select name="country_code" :label="__('Country')" :options="$countries" :value="$property?->country_code ?? 'BD'" required /></div>
                    </div>
                </x-card>

                <x-card :title="__('Operations')" icon="bi-clock">
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="timezone" :label="__('Timezone')" :options="$timezones" :value="$property?->timezone ?? 'Asia/Dhaka'" required /></div>
                        <div class="col-md-6"><x-form.select name="currency_code" :label="__('Operating currency')" :options="$currencies" :value="$property?->currency_code ?? 'BDT'" required /></div>
                        <div class="col-md-4"><x-form.input name="check_in_time" type="time" :label="__('Check-in time')" :value="$property ? substr($property->check_in_time, 0, 5) : '14:00'" required /></div>
                        <div class="col-md-4"><x-form.input name="check_out_time" type="time" :label="__('Check-out time')" :value="$property ? substr($property->check_out_time, 0, 5) : '12:00'" required /></div>
                        <div class="col-md-4">
                            <label class="form-label">{{ __('Business date') }}</label>
                            <div class="form-control-plaintext">{{ $property?->business_date->format('d M Y') ?? __('Today, in the property\'s timezone') }}</div>
                            <div class="form-text">{{ __('Moves on with the night audit.') }}</div>
                        </div>
                    </div>
                </x-card>
            </div>

            <div class="col-xl-4">
                <x-card :title="__('Logo')" icon="bi-image">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ __('Logo') }}" class="img-fluid rounded border mb-3" data-property-logo>
                    @endif
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" @class(['form-control', 'is-invalid' => $errors->has('logo')]) aria-label="{{ __('Logo') }}">
                    @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('PNG, JPG or WebP, up to 2 MB. Used on invoices and vouchers.') }}</div>
                </x-card>

                @if ($property)
                    <x-audit-trail :entries="$history" />
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('property.properties.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save property') }}</button>
        </div>
    </form>
</x-layouts::app>
