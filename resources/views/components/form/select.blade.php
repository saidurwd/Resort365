@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'id' => null,
    'placeholder' => null,
    'required' => false,
    'help' => null,
    'multiple' => false,
    'search' => true,
    'tomOptions' => [],
    'wrapperClass' => null,
    'errorBag' => 'default',
])

{{--
    <x-form.select name="cottage_type_id" :label="__('Cottage type')" :options="$types" :value="$cottage->cottage_type_id" />
    `options` is value => label. `search` enhances the select with Tom Select; `tomOptions` are passed to it.
--}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $key = \App\Support\Ui\FormField::key($name);
    $id ??= \App\Support\Ui\FormField::id($name);
    $invalid = $errors->getBag($errorBag)->has($key);
    $selected = collect(old($key, $value))->map(fn ($v) => (string) $v)->all();
    $fieldName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp

<x-form.field :name="$name" :label="$label" :id="$id" :required="$required" :help="$help" :error-bag="$errorBag" :class="$wrapperClass">
    <select
        name="{{ $fieldName }}"
        id="{{ $id }}"
        @required($required)
        @if ($multiple) multiple @endif
        @if ($search) data-tom-select="{{ json_encode((object) $tomOptions) }}" @endif
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        {{ $attributes->class(['form-select', 'is-invalid' => $invalid]) }}
    >
        @if (! $multiple)
            <option value="">{{ $placeholder ?? __('Select…') }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $selected, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
</x-form.field>
