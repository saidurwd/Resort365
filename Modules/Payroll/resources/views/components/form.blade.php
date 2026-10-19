@php($title = $record ? $record->name : __('New component'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Payroll') => null, __('Salary components') => route('payroll.components.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('payroll.components.update', $record) : route('payroll.components.store') }}" data-component-form>
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card>
            @if ($record?->is_system)
                <div class="alert alert-info py-2">{{ __('This is a system component: payroll relies on its code and kind, so only its name, tax treatment and order can change.') }}</div>
            @endif
            <div class="row">
                <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required :readonly="(bool) $record?->is_system" :help="__('Capital letters, digits and underscores. Formulas use it.')" /></div>
                <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                <div class="col-md-4"><x-form.select name="kind" :label="__('Kind')" :options="$kinds" :value="$record?->kind->value ?? 'earning'" required :search="false" /></div>
                <div class="col-md-4"><x-form.select name="frequency" :label="__('Paid')" :options="$frequencies" :value="$record?->frequency->value ?? 'monthly'" required :search="false" /></div>
                <div class="col-md-4"><x-form.input name="annual_times" type="number" min="1" max="12" :label="__('Times a year (if not monthly)')" :value="$record?->annual_times ?? 1" /></div>
                <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Order on the payslip')" :value="$record?->sort_order ?? 100" /></div>
            </div>
            @foreach (['is_taxable' => [__('Taxable (earnings only)'), false], 'is_active' => [__('Active'), true]] as $field => [$label, $default])
                <div class="form-check form-switch mb-1">
                    <input type="hidden" name="{{ $field }}" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="{{ $field }}" id="field-{{ $field }}" value="1" @checked(old($field, $record?->{$field} ?? $default))>
                    <label class="form-check-label" for="field-{{ $field }}">{{ $label }}</label>
                </div>
            @endforeach
        </x-card>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('payroll.components.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
