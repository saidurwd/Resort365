@php($months = $budget->fiscalYear->periods->pluck('name', 'number')->map(fn (string $name): string => mb_substr($name, 0, 3))->all())
<x-layouts::app :title="$budget->name" :subtitle="$budget->fiscalYear->name.' · '.$scope" :breadcrumbs="[__('Accounting') => null, __('Budgets') => route('accounting.budgets.index'), $budget->name => null]">
    <x-slot:actions>
        <a href="{{ route('accounting.budgets.report', $budget) }}" class="btn btn-sm btn-outline-primary" data-budget-report><i class="bi bi-graph-up"></i> {{ __('Against actual') }}</a>
        @if ($canManage)
            <form method="POST" action="{{ route('accounting.budgets.destroy', $budget) }}" class="d-inline" data-confirm="{{ __('Delete this budget?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-delete><i class="bi bi-trash"></i></button></form>
        @endif
    </x-slot:actions>

    @if ($canManage && $years !== [])
        <x-card :title="__('Copy from the ledger')" icon="bi-files">
            <form method="POST" action="{{ route('accounting.budgets.copy', $budget) }}" class="d-flex flex-wrap gap-2 align-items-end" data-copy-form data-confirm="{{ __('Replace the lines?') }}" data-confirm-text="{{ __('The lines of this budget are replaced by last year\'s actuals plus the change.') }}">
                @csrf
                <x-form.select name="from_year_id" :label="__('Actuals of')" :options="$years" :search="false" wrapper-class="mb-0" />
                <x-form.input name="change" type="number" step="0.1" min="-100" max="1000" :label="__('Change (%)')" value="0" wrapper-class="mb-0" />
                <button type="submit" class="btn btn-outline-secondary">{{ __('Copy the actuals') }}</button>
            </form>
        </x-card>
    @endif

    <form method="POST" action="{{ route('accounting.budgets.lines', $budget) }}" data-lines-form
        x-data="budgetGrid(Object.assign(@js($lines), { accountTypes: @js(collect($accounts)->pluck('type', 'id')) }))">
        @csrf @method('PUT')
        <x-card :title="__('Lines')" icon="bi-table" body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-budget-lines>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Account') }}</th><th>{{ __('Department') }}</th>
                            @foreach ($months as $short)<th class="text-end">{{ $short }}</th>@endforeach
                            <th class="text-end">{{ __('Year') }}</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(row, index) in rows" :key="index">
                            <tr data-line>
                                <td class="ps-3">
                                    <select class="form-select form-select-sm" :name="`lines[${index}][account_id]`" x-model="row.account_id" required>
                                        <option value="">{{ __('Choose an account') }}</option>
                                        @foreach ($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['label'] }}</option>@endforeach
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" :name="`lines[${index}][department_id]`" x-model="row.department_id">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                    </select>
                                </td>
                                <template x-for="(amount, month) in row.months" :key="month">
                                    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end budget-amount" :name="`lines[${index}][months][${month}]`" x-model="row.months[month]" @dblclick="fill(index)"></td>
                                </template>
                                <td class="text-end font-monospace" x-text="format(total(row))"></td>
                                <td class="pe-2"><button type="button" class="btn btn-sm btn-outline-danger" @click="remove(index)" data-remove-line><i class="bi bi-x"></i></button></td>
                            </tr>
                        </template>
                        <tr x-show="rows.length === 0"><td colspan="16"><x-empty-state :title="__('No lines yet')" :message="__('Add the accounts you plan, or copy last year\'s actuals above.')" /></td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold" data-totals="income"><td class="ps-3" colspan="2">{{ __('Income') }}</td><template x-for="month in 12"><td class="text-end font-monospace" x-text="format(column(month - 1, 'income'))"></td></template><td></td><td></td></tr>
                        <tr class="fw-semibold" data-totals="expense"><td class="ps-3" colspan="2">{{ __('Expenses') }}</td><template x-for="month in 12"><td class="text-end font-monospace" x-text="format(column(month - 1, 'expense'))"></td></template><td></td><td></td></tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
        @if ($canManage)
            <div class="mt-3 d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" @click="add()" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add a line') }}</button>
                <button type="submit" class="btn btn-primary" data-save-lines>{{ __('Save the lines') }}</button>
                <span class="small text-body-secondary align-self-center">{{ __('Double-click a month to copy it to the whole year.') }}</span>
            </div>
        @endif
    </form>
</x-layouts::app>
