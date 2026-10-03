@php
    $title = $guest ? $guest->full_name : __('New guest');
    $duplicates = session('duplicates', []);
@endphp

<x-layouts::app :title="$title" :breadcrumbs="[__('Guests') => route('guest.guests.index'), $title => null]">
    <form method="POST" action="{{ $guest ? route('guest.guests.update', $guest) : route('guest.guests.store') }}">
        @csrf
        @if ($guest)
            @method('PUT')
        @endif

        @if ($duplicates !== [])
            <div class="alert alert-warning" role="alert" data-duplicate-warning>
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> {{ __('This guest may already exist') }}</h5>
                <p>{{ __('These guests have the same details. Open one of them instead, or confirm that this is a different person.') }}</p>
                <ul class="mb-3">
                    @foreach ($duplicates as $duplicate)
                        <li data-duplicate>
                            <a href="{{ $duplicate['url'] }}" target="_blank" rel="noopener" class="fw-semibold">{{ $duplicate['name'] }}</a>
                            <span class="text-body-secondary">{{ collect([$duplicate['phone'], $duplicate['email']])->filter()->implode(' · ') }}</span>
                            @foreach ($duplicate['matched'] as $match)
                                <span class="badge text-bg-warning">{{ match ($match) { 'phone' => __('Same phone'), 'email' => __('Same email'), default => __('Same ID') } }}</span>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="confirm_duplicate" value="1" id="field-confirm_duplicate">
                    <label class="form-check-label" for="field-confirm_duplicate">{{ __('This is a different person: create the guest anyway') }}</label>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Guest')" icon="bi-person">
                    <div class="row">
                        <div class="col-md-2"><x-form.select name="title" :label="__('Title')" :options="array_combine(\Modules\Guest\Http\Requests\SaveGuestRequest::TITLES, \Modules\Guest\Http\Requests\SaveGuestRequest::TITLES)" :value="$guest?->title" :placeholder="'—'" :search="false" /></div>
                        <div class="col-md-5"><x-form.input name="first_name" :label="__('First name')" :value="$guest?->first_name" required /></div>
                        <div class="col-md-5"><x-form.input name="last_name" :label="__('Last name')" :value="$guest?->last_name" /></div>
                        <div class="col-md-6"><x-form.input name="phone" type="tel" :label="__('Phone')" :value="$guest?->phone" :help="__('Local numbers get the default country code.')" /></div>
                        <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" :value="$guest?->email" /></div>
                        <div class="col-md-6"><x-form.select name="nationality_code" :label="__('Nationality')" :options="$countries" :value="$guest?->nationality_code" :placeholder="__('Choose…')" /></div>
                        <div class="col-md-6"><x-form.date name="date_of_birth" :label="__('Date of birth')" :value="$guest?->date_of_birth?->toDateString()" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Identity document')" icon="bi-person-badge">
                    <div class="row">
                        <div class="col-md-4"><x-form.select name="id_type" :label="__('ID type')" :options="\Modules\Guest\Enums\IdType::options()" :value="$guest?->id_type?->value" :placeholder="__('None')" :search="false" /></div>
                        <div class="col-md-4">
                            <x-form.input name="id_number" :label="__('ID number')" autocomplete="off"
                                :help="$guest?->id_number ? __('Stored encrypted. Leave empty to keep :masked.', ['masked' => $guest->maskedIdNumber()]) : __('Stored encrypted.')" />
                        </div>
                        <div class="col-md-4"><x-form.date name="id_expiry" :label="__('Expiry')" :value="$guest?->id_expiry?->toDateString()" /></div>
                    </div>
                    @unless ($guest)
                        <p class="small text-body-secondary mb-0">{{ __('Upload ID scans on the guest page after saving.') }}</p>
                    @endunless
                </x-card>

                <x-card :title="__('Address')" icon="bi-geo-alt">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="address[line1]" :label="__('Address line 1')" :value="$guest?->address['line1'] ?? null" /></div>
                        <div class="col-md-6"><x-form.input name="address[line2]" :label="__('Address line 2')" :value="$guest?->address['line2'] ?? null" /></div>
                        <div class="col-md-4"><x-form.input name="address[city]" :label="__('City')" :value="$guest?->address['city'] ?? null" /></div>
                        <div class="col-md-4"><x-form.input name="address[postal_code]" :label="__('Postal code')" :value="$guest?->address['postal_code'] ?? null" /></div>
                        <div class="col-md-4"><x-form.select name="address[country_code]" :label="__('Country')" :options="$countries" :value="$guest?->address['country_code'] ?? null" :placeholder="__('Choose…')" /></div>
                    </div>
                </x-card>
            </div>

            <div class="col-xl-4">
                <x-card :title="__('Profile')" icon="bi-star">
                    <x-form.select name="company_id" :label="__('Company')" :options="$companies" :value="$guest?->company_id" :placeholder="__('None')" />
                    <x-form.select name="vip_level" :label="__('VIP level')" :options="\Modules\Guest\Enums\VipLevel::options()" :value="$guest?->vip_level->value ?? 'none'" required :search="false" />
                    <x-form.select name="preferences" :label="__('Preferences')" :options="collect(old('preferences', $guest?->preferences ?? []))->mapWithKeys(fn ($p) => [$p => $p])->all()" :value="$guest?->preferences ?? []" multiple :tom-options="['create' => true, 'persist' => false]" :help="__('Type and press Enter, e.g. Non-smoking.')" />
                    <x-form.field name="notes" :label="__('Notes')">
                        <textarea name="notes" id="field-notes" rows="3" @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $guest?->notes) }}</textarea>
                    </x-form.field>
                    <div class="form-check form-switch">
                        <input type="hidden" name="marketing_consent" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="marketing_consent" id="field-marketing_consent" value="1" @checked(old('marketing_consent', $guest?->marketing_consent))>
                        <label class="form-check-label" for="field-marketing_consent">{{ __('Agrees to receive offers') }}</label>
                    </div>
                </x-card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ $guest ? route('guest.guests.show', $guest) : route('guest.guests.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save guest') }}</button>
        </div>
    </form>
</x-layouts::app>
