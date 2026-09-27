@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'id' => null,
    'required' => false,
    'help' => null,
    'prepend' => null,
    'append' => null,
    'wrapperClass' => null,
])

{{-- <x-form.input name="email" type="email" :label="__('Email')" required :value="$guest->email" /> --}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $key = \App\Support\Ui\FormField::key($name);
    $id ??= \App\Support\Ui\FormField::id($name);
    $invalid = $errors->has($key);
    $describedBy = trim(($invalid ? $id.'-error ' : '').($help ? $id.'-help' : ''));
@endphp

<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :help="$help" :class="$wrapperClass">
    @if ($prepend || $append)<div @class(['input-group', 'has-validation' => $invalid])>@endif
        @if ($prepend)<span class="input-group-text">{{ $prepend }}</span>@endif

        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $id }}"
            @if ($type !== 'password') value="{{ old($key, $value) }}" @endif
            @required($required)
            @if ($invalid) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->class(['form-control', 'is-invalid' => $invalid]) }}
        >

        @if ($append)<span class="input-group-text">{{ $append }}</span>@endif
    @if ($prepend || $append)</div>@endif
</x-form.field>
