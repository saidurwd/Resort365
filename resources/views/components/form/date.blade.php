@props([
    'name',
    'label' => null,
    'value' => null,
    'id' => null,
    'required' => false,
    'help' => null,
    'min' => null,
    'max' => null,
    'time' => false,
    'range' => false,
    'pickerOptions' => [],
    'wrapperClass' => null,
])

{{--
    Date picker (flatpickr). Submits Y-m-d (or Y-m-d H:i with `time`), displays "27 Sep 2026".
    <x-form.date name="arrival_date" :label="__('Arrival')" required :min="today()->toDateString()" />
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $key = \App\Support\Ui\FormField::key($name);
    $id ??= \App\Support\Ui\FormField::id($name);
    $invalid = $errors->has($key);
    $current = old($key, $value instanceof \DateTimeInterface ? $value->format($time ? 'Y-m-d H:i' : 'Y-m-d') : $value);
    $options = array_filter([
        'minDate' => $min,
        'maxDate' => $max,
        'enableTime' => $time ?: null,
        'time_24hr' => $time ?: null,
        'dateFormat' => $time ? 'Y-m-d H:i' : null,
        'altFormat' => $time ? 'd M Y H:i' : null,
        'mode' => $range ? 'range' : null,
    ], fn ($option) => $option !== null) + $pickerOptions;
@endphp

<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :help="$help" :class="$wrapperClass">
    <div @class(['input-group', 'has-validation' => $invalid])>
        <span class="input-group-text"><i class="bi bi-calendar3"></i></span>
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $id }}"
            value="{{ $current }}"
            autocomplete="off"
            data-flatpickr="{{ json_encode((object) $options) }}"
            @required($required)
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $invalid]) }}
        >
    </div>
</x-form.field>
