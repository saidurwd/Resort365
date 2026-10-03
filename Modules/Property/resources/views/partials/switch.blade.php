{{-- A boolean field as a switch: @include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active'), 'checked' => true]) --}}
<div class="form-check form-switch mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" id="field-{{ $name }}" value="1" @checked(old($name, $checked))>
    <label class="form-check-label" for="field-{{ $name }}">{{ $label }}</label>
</div>
