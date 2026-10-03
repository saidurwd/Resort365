{{-- Types, date range and weekdays shared by the rate grid's bulk forms. --}}
<x-form.select name="units" :id="$prefix.'-units'" :label="__('Room / cottage types')" :options="$unitOptions" :value="old('units', ['*'])" multiple required />
<div class="row">
    <div class="col-6"><x-form.date name="from" :id="$prefix.'-from'" :label="__('From')" :value="$from" required /></div>
    <div class="col-6"><x-form.date name="to" :id="$prefix.'-to'" :label="__('To')" :value="$to" required /></div>
</div>
<div class="mb-3">
    <span class="form-label d-block">{{ __('On') }}</span>
    @foreach ($dayNames as $iso => $name)
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="days[]" value="{{ $iso }}" id="{{ $prefix }}-day-{{ $iso }}" @checked(in_array($iso, array_map('intval', old('days', range(1, 7))), true))>
            <label class="form-check-label" for="{{ $prefix }}-day-{{ $iso }}">{{ $name }}</label>
        </div>
    @endforeach
    @error('days')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
