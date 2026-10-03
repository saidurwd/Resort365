{{-- Contact and address fields shared by companies and travel agents. --}}
<div class="row">
    <div class="col-md-4"><x-form.input name="contact_person" :label="__('Contact person')" :value="$record?->contact_person" /></div>
    <div class="col-md-4"><x-form.input name="email" type="email" :label="__('Email')" :value="$record?->email" /></div>
    <div class="col-md-4"><x-form.input name="phone" type="tel" :label="__('Phone')" :value="$record?->phone" /></div>
    <div class="col-md-6"><x-form.input name="address[line1]" :label="__('Address')" :value="$record?->address['line1'] ?? null" /></div>
    <div class="col-md-3"><x-form.input name="address[city]" :label="__('City')" :value="$record?->address['city'] ?? null" /></div>
    <div class="col-md-3"><x-form.input name="address[country_code]" :label="__('Country code')" :value="$record?->address['country_code'] ?? 'BD'" /></div>
</div>
