<x-layouts::app :title="__('New budget')" :breadcrumbs="[__('Accounting') => null, __('Budgets') => route('accounting.budgets.index'), __('New') => null]">
    <div class="row"><div class="col-xl-6">
        <x-card :title="__('A year\'s budget')" icon="bi-clipboard-data">
            <form method="POST" action="{{ route('accounting.budgets.store') }}" data-budget-form>
                @csrf
                <x-form.select name="fiscal_year_id" :label="__('Fiscal year')" :options="$years" required :search="false" />
                <x-form.select name="property_id" :label="__('Property')" :options="$properties" :placeholder="__('All properties (whole company)')" :search="false" :help="__('A property\'s budget is compared with that property\'s ledger lines; a company-wide budget with all of them.')" />
                <x-form.input name="name" :label="__('Name')" required />
                <x-form.input name="notes" :label="__('Notes')" />
                <button type="submit" class="btn btn-primary">{{ __('Make the budget') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::app>
