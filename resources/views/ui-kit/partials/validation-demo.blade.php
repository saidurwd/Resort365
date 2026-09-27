{{-- Components read $errors from shared view data, so share a fake bag while rendering the demo, then restore it. --}}
@php
    $realErrors = view()->shared('errors');
    view()->share('errors', (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag([
        'phone' => [__('The phone number is already used by another guest.')],
        'nights' => [__('Choose at least one night.')],
        'room_type' => [__('Select a room type.')],
        'rate' => [__('The rate must be a valid amount.')],
    ])));
@endphp

<div class="row">
    <div class="col-md-6"><x-form.input name="phone" :label="__('Phone')" value="01711-000000" required /></div>
    <div class="col-md-6"><x-form.input name="nights" type="number" :label="__('Nights')" value="0" /></div>
    <div class="col-md-6"><x-form.select name="room_type" :label="__('Room type')" :options="['deluxe-king' => __('Deluxe King'), 'twin' => __('Twin')]" required /></div>
    <div class="col-md-6"><x-form.money name="rate" :label="__('Rate')" currency="BDT" value="95OO" /></div>
</div>

@php
    view()->share('errors', $realErrors);
@endphp
