@php($title = $record ? $record->name : __('New salary structure'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Payroll') => null, __('Salary structures') => route('payroll.structures.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('payroll.structures.update', $record) : route('payroll.structures.store') }}" data-structure-form
          x-data="{ lines: @js(array_values($lines)), add() { this.lines.push({ salary_component_id: '', calc_type: 'fixed', amount: '', percent: '', percent_of: '', formula: '' }); } }">
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <x-card :title="__('Structure')" icon="bi-diagram-3">
            <div class="row">
                <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required /></div>
                <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                <div class="col-md-4"><x-form.input name="description" :label="__('Description')" :value="$record?->description" /></div>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $record?->is_active ?? true))>
                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
            </div>
        </x-card>

        <x-card :title="__('Components')" icon="bi-list-check">
            <p class="small text-body-secondary">{{ __('BASIC is the employee\'s basic salary. A percentage names the component it is taken of (e.g. BASIC); a formula may use component codes, + − × ÷, brackets and MIN, MAX, ROUND, ABS, e.g. MIN(BASIC * 0.1, 10000). "Set for each employee" takes the amount from the employee\'s salary, with the amount here as the usual one. Income tax (TAX) is worked out by the tax table.') }}</p>
            <template x-for="(line, index) in lines" :key="index">
                <div class="row g-2 align-items-end mb-2" data-structure-line>
                    <div class="col-md-3">
                        <label class="form-label small">{{ __('Component') }}</label>
                        <select class="form-select form-select-sm" :name="`lines[${index}][salary_component_id]`" x-model="line.salary_component_id" required>
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($components as $component)<option value="{{ $component['id'] }}">{{ $component['text'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">{{ __('Worked out as') }}</label>
                        <select class="form-select form-select-sm" :name="`lines[${index}][calc_type]`" x-model="line.calc_type">
                            @foreach ($calcTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2" x-show="line.calc_type === 'fixed' || line.calc_type === 'employee'">
                        <label class="form-label small">{{ __('Amount') }}</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][amount]`" x-model="line.amount">
                    </div>
                    <div class="col-md-2" x-show="line.calc_type === 'percent'">
                        <label class="form-label small">{{ __('Percent') }}</label>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][percent]`" x-model="line.percent">
                    </div>
                    <div class="col-md-2" x-show="line.calc_type === 'percent'">
                        <label class="form-label small">{{ __('Of') }}</label>
                        <input type="text" class="form-control form-control-sm font-monospace" :name="`lines[${index}][percent_of]`" x-model="line.percent_of" placeholder="BASIC">
                    </div>
                    <div class="col-md-4" x-show="line.calc_type === 'formula'">
                        <label class="form-label small">{{ __('Formula') }}</label>
                        <input type="text" class="form-control form-control-sm font-monospace" :name="`lines[${index}][formula]`" x-model="line.formula" placeholder="BASIC / 12">
                    </div>
                    <div class="col-auto"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="add()" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add a component') }}</button>
            @error('lines')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </x-card>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ $record ? route('payroll.structures.show', $record) : route('payroll.structures.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save structure') }}</button>
        </div>
    </form>
</x-layouts::app>
