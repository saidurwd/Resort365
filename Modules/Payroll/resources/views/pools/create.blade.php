<x-layouts::app :title="__('Work out a pool')" :subtitle="$subtitle" :breadcrumbs="[__('Payroll') => null, __('Service charge and tips') => route('payroll.pools.index'), __('New') => null]">
    <div class="row"><div class="col-xl-6">
        <x-card :title="__('Service charge or tips for a month')" icon="bi-piggy-bank">
            <form method="POST" action="{{ route('payroll.pools.store') }}" data-pool-form>
                @csrf
                <x-form.select name="month" :label="__('Month')" :options="$months" required :search="false" />
                <x-form.select name="kind" :label="__('Pool')" :options="$kinds" value="service_charge" required :search="false" />
                <x-form.select name="rule" :label="__('Shared')" :options="$rules" :value="$rule" required :search="false" :help="__('Equal shares, by each person\'s points (Who shares), or by the days they worked.')" />
                <x-form.input name="staff_percent" type="number" step="0.01" min="0" max="100" :label="__('Share for staff (%)')" :help="__('Leave empty to use the setting: :service% of the service charge and :tips% of the tips.', ['service' => $percents['service_charge'], 'tips' => $percents['tips']])" />
                <p class="small text-body-secondary">{{ __('What was collected is read from the ledger: the service charge and the tips held for staff in that month.') }}</p>
                <button type="submit" class="btn btn-primary">{{ __('Work out the shares') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::app>
