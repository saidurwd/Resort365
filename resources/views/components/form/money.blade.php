@props([
    'name',
    'label' => null,
    'value' => null,
    'currency' => null,
    'id' => null,
    'required' => false,
    'help' => null,
    'allowNegative' => false,
    'wrapperClass' => null,
    'errorBag' => 'default',
])

{{--
    Money input. Values stay decimal strings (never floats); the server validates and does all arithmetic.
    <x-form.money name="deposit_amount" :label="__('Deposit')" currency="BDT" :value="$reservation->deposit_amount" />
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $key = \App\Support\Ui\FormField::key($name);
    $id ??= \App\Support\Ui\FormField::id($name);
    $invalid = $errors->getBag($errorBag)->has($key);
    $pattern = ($allowNegative ? '-?' : '').'\d+(\.\d{1,2})?';
@endphp

<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :help="$help" :error-bag="$errorBag" :class="$wrapperClass">
    <div @class(['input-group', 'has-validation' => $invalid])>
        @if ($currency)<span class="input-group-text">{{ $currency }}</span>@endif
        <input
            type="text"
            inputmode="decimal"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ old($key, $value) }}"
            pattern="{{ $pattern }}"
            placeholder="0.00"
            autocomplete="off"
            @required($required)
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['form-control', 'input-money', 'is-invalid' => $invalid]) }}
        >
    </div>
</x-form.field>
