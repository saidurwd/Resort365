@php($title = $tax ? $tax->name : __('New tax'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Taxes') => route('core.taxes.index'), $title => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ $tax ? route('core.taxes.update', $tax) : route('core.taxes.store') }}">
                @csrf
                @if ($tax)
                    @method('PUT')
                @endif
                <x-card :title="__('Tax')" icon="bi-percent">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$tax?->code" required :help="__('E.g. VAT, SC.')" /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$tax?->name" required :help="__('Shown on invoices and bills.')" /></div>
                        <div class="col-md-6"><x-form.select name="type" :label="__('Type')" :options="\Modules\Core\Enums\TaxType::options()" :value="$tax?->type->value ?? 'percent'" required :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="rate" :label="__('Rate')" :value="$tax?->rate" required inputmode="decimal" :help="__('A percentage (15 = 15%), or the amount per unit for a fixed tax.')" /></div>
                        <div class="col-md-6"><x-form.input name="sort_order" type="number" min="0" :label="__('Calculation order')" :value="$tax?->sort_order ?? 10" required :help="__('Lower numbers are calculated first.')" /></div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="is_compound" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_compound" id="field-is_compound" value="1" @checked(old('is_compound', $tax?->is_compound))>
                        <label class="form-check-label" for="field-is_compound">{{ __('Compound: calculate on the net amount plus the taxes before it (e.g. VAT on room + service charge)') }}</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $tax?->is_active ?? true))>
                        <label class="form-check-label" for="field-is_active">{{ __('Active (an inactive tax is no longer charged)') }}</label>
                    </div>
                    <x-form.field name="description" :label="__('Description')">
                        <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $tax?->description) }}</textarea>
                    </x-form.field>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.taxes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save tax') }}</button>
                </div>
            </form>
        </div>
        @if ($tax)
            <div class="col-xl-5"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
