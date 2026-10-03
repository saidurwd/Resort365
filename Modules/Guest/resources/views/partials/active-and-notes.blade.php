<x-form.field name="notes" :label="__('Notes')">
    <textarea name="notes" id="field-notes" rows="2" @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $record?->notes) }}</textarea>
</x-form.field>
<div class="form-check form-switch">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
    <label class="form-check-label" for="field-is_active">{{ __('Active (can be chosen on new bookings)') }}</label>
</div>
