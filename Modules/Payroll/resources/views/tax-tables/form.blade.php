@php($title = $record ? $record->name : __('New tax table'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Payroll') => null, __('Income tax tables') => route('payroll.tax-tables.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('payroll.tax-tables.update', $record) : route('payroll.tax-tables.store') }}" data-tax-table-form
          x-data="{ thresholds: @js(array_values($thresholds)), minimums: @js(array_values($minimums)), slabs: @js(array_values($slabs)) }">
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Tax table')" icon="bi-percent">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                        <div class="col-md-3"><x-form.input name="tax_year" :label="__('Tax year')" :value="$record?->tax_year" required /></div>
                        <div class="col-md-3"><x-form.date name="effective_from" :label="__('Effective from')" :value="$record?->effective_from->toDateString()" required /></div>
                        <div class="col-md-6"><x-form.input name="exemption_fraction" :label="__('Salary exemption')" :value="$record?->exemption_fraction ?? '1/3'" required :help="__('A fraction such as 1/3, or a decimal such as 0.25.')" /></div>
                        <div class="col-md-6"><x-form.input name="exemption_cap" type="number" step="0.01" min="0" :label="__('Exemption at most')" :value="$record?->exemption_cap ?? '450000'" required /></div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
                        <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                    </div>
                </x-card>
                <x-card :title="__('Slabs above the tax-free amount')" icon="bi-bar-chart-steps">
                    <p class="small text-body-secondary">{{ __('From the tax-free amount upwards, each band has a width and a rate. The last band has no width: it covers everything above.') }}</p>
                    <template x-for="(slab, index) in slabs" :key="index">
                        <div class="row g-2 mb-2" data-slab>
                            <div class="col-5"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`slabs[${index}][width]`" x-model="slab.width" placeholder="{{ __('Width (empty = the rest)') }}"></div>
                            <div class="col-4"><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm text-end" :name="`slabs[${index}][rate]`" x-model="slab.rate" placeholder="{{ __('Rate %') }}"></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="slabs.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="slabs.push({ width: '', rate: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add a slab') }}</button>
                </x-card>
            </div>
            <div class="col-xl-6">
                <x-card :title="__('Tax-free amount by taxpayer category')" icon="bi-people">
                    <template x-for="(row, index) in thresholds" :key="index">
                        <div class="row g-2 mb-2" data-threshold>
                            <div class="col-3"><input type="text" class="form-control form-control-sm font-monospace" :name="`thresholds[${index}][code]`" x-model="row.code" placeholder="general"></div>
                            <div class="col-5"><input type="text" class="form-control form-control-sm" :name="`thresholds[${index}][label]`" x-model="row.label" placeholder="{{ __('Label') }}"></div>
                            <div class="col-3"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`thresholds[${index}][amount]`" x-model="row.amount"></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="thresholds.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="thresholds.push({ code: '', label: '', amount: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add a category') }}</button>
                </x-card>
                <x-card :title="__('Minimum tax by tax area')" icon="bi-geo-alt">
                    <template x-for="(row, index) in minimums" :key="index">
                        <div class="row g-2 mb-2" data-minimum>
                            <div class="col-3"><input type="text" class="form-control form-control-sm font-monospace" :name="`minimum_tax[${index}][code]`" x-model="row.code" placeholder="metro"></div>
                            <div class="col-5"><input type="text" class="form-control form-control-sm" :name="`minimum_tax[${index}][label]`" x-model="row.label" placeholder="{{ __('Label') }}"></div>
                            <div class="col-3"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`minimum_tax[${index}][amount]`" x-model="row.amount"></div>
                            <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="minimums.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="minimums.push({ code: '', label: '', amount: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add an area') }}</button>
                </x-card>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('payroll.tax-tables.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save tax table') }}</button>
        </div>
    </form>
</x-layouts::app>
