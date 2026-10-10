{{--
    A quick income or expense voucher (ARCHITECTURE §5.14): saved, it is posted to the ledger at once. Receipts
    and invoices are attached on the voucher page.
--}}
@php($income = $type === \Modules\Accounting\Enums\VoucherType::Income)
<x-layouts::app :title="$income ? __('New income voucher') : __('New expense voucher')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Vouchers') => route('accounting.vouchers.index'), ($income ? __('Income') : __('Expense')) => null]">
    <form method="POST" action="{{ route('accounting.vouchers.store') }}" data-voucher-form>
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">

        <x-card :title="$income ? __('Money received') : __('Money paid')" :icon="$income ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up'">
            <div class="row">
                <div class="col-md-3"><x-form.date name="voucher_date" :label="__('Date')" :value="old('voucher_date', now()->toDateString())" required /></div>
                <div class="col-md-5"><x-form.select name="account_id" :label="$income ? __('Income account') : __('Expense account')" :options="$accounts" :value="old('account_id')" :placeholder="__('Choose an account')" required /></div>
                <div class="col-md-4"><x-form.select name="cash_account_id" :label="$income ? __('Received into') : __('Paid from')" :options="$cashAccounts" :value="old('cash_account_id')" :placeholder="__('Choose cash or bank')" required /></div>
            </div>
            <div class="row">
                <div class="col-md-3"><x-form.input name="amount" type="number" step="0.01" min="0.01" :label="$income ? __('Amount received') : __('Amount paid (VAT included)')" :value="old('amount')" required /></div>
                @unless ($income)
                    <div class="col-md-3"><x-form.input name="tax_amount" type="number" step="0.01" min="0" :label="__('of which input VAT')" :value="old('tax_amount')" /></div>
                @endunless
                <div class="col-md-3"><x-form.input name="payee" :label="$income ? __('Received from') : __('Paid to')" :value="old('payee')" /></div>
                <div class="col-md-3"><x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="old('department_id')" :placeholder="__('None')" /></div>
            </div>
            <div class="row">
                <div class="col-md-8"><x-form.input name="description" :label="__('Description')" :value="old('description')" required /></div>
                <div class="col-md-4"><x-form.input name="reference" :label="__('Reference (invoice or receipt number)')" :value="old('reference')" /></div>
            </div>
        </x-card>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-post-voucher><i class="bi bi-check2-circle"></i> {{ __('Save and post') }}</button>
            <a href="{{ route('accounting.vouchers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts::app>
