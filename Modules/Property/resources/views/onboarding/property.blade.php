<x-layouts::app :title="__('Your resort')" :breadcrumbs="[__('Setup checklist') => route('core.onboarding.index'), __('Your resort') => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('property.onboarding.property.store') }}">
                @csrf
                <x-card :title="__('The resort')" icon="bi-building">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Resort name')" required /></div>
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" required :help="__('Short, e.g. CXB. Used on documents.')" /></div>
                        <div class="col-md-6"><x-form.input name="phone" :label="__('Phone')" /></div>
                        <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" /></div>
                        <div class="col-md-6"><x-form.input name="address_line1" :label="__('Address')" /></div>
                        <div class="col-md-6"><x-form.input name="city" :label="__('City')" /></div>
                        <div class="col-md-6"><x-form.select name="country_code" :label="__('Country')" :options="$countries" value="BD" required /></div>
                        <div class="col-md-6"><x-form.select name="timezone" :label="__('Timezone')" :options="$timezones" :value="$timezone" required /></div>
                        <div class="col-md-6"><x-form.select name="currency_code" :label="__('Currency')" :options="$currencies" :value="$currency" required /></div>
                        <div class="col-md-3"><x-form.input name="check_in_time" type="time" :label="__('Check-in time')" value="14:00" required /></div>
                        <div class="col-md-3"><x-form.input name="check_out_time" type="time" :label="__('Check-out time')" value="12:00" required /></div>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.onboarding.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Create the resort') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
