<x-layouts::app :title="__('New loan or advance')" :breadcrumbs="[__('Payroll') => null, __('Loans and advances') => route('payroll.loans.index'), __('New') => null]">
    <div class="row"><div class="col-xl-6">
        <x-card :title="__('Loan or salary advance')" icon="bi-wallet2">
            <form method="POST" action="{{ route('payroll.loans.store') }}" data-loan-form>
                @csrf
                <x-form.select name="employee_id" :label="__('Employee')" :options="$chosen ? [$chosen->id => $chosen->employeeNo.' · '.$chosen->name] : []" :value="$chosen?->id" :placeholder="__('Type a name or number…')" :tom-options="['remote' => route('hr.employees.search')]" required />
                <div class="row">
                    <div class="col-md-6"><x-form.select name="kind" :label="__('Kind')" :options="$kinds" value="loan" required :search="false" /></div>
                    <div class="col-md-6"><x-form.input name="principal" type="number" step="0.01" min="0" :label="__('Amount')" required /></div>
                    <div class="col-md-4"><x-form.input name="installments" type="number" min="1" max="36" :label="__('Installments')" value="6" required :help="__('Up to 36 for a loan, 3 for an advance.')" /></div>
                    <div class="col-md-4"><x-form.input name="first_month" type="month" :label="__('First deduction')" :value="now()->addMonth()->format('Y-m')" required /></div>
                    <div class="col-md-4"><x-form.date name="disbursed_on" :label="__('Paid on')" :value="$today" required /></div>
                </div>
                <x-form.select name="payment_method" :label="__('Paid by')" :options="$methods" value="cash" required :search="false" />
                <x-form.input name="reason" :label="__('Reason')" />
                <button type="submit" class="btn btn-primary">{{ __('Pay out and schedule') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::app>
