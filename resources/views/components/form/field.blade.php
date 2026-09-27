@props([
    'name',
    'label' => null,
    'id' => null,
    'required' => false,
    'help' => null,
    'errorBag' => 'default',
])

{{-- Shared wrapper: label with required marker, the control (slot), help text and the validation error. --}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $key = \App\Support\Ui\FormField::key($name);
    $id ??= \App\Support\Ui\FormField::id($name);
@endphp

<div {{ $attributes->class(['mb-3']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}@if ($required)<span class="required-marker" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @error($key, $errorBag)
        <div class="invalid-feedback d-block" id="{{ $id }}-error">{{ $message }}</div>
    @enderror

    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif
</div>
