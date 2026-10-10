<x-layouts::app :title="__('New transfer')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Transfers') => route('accounting.transfers.index'), __('New') => null]">
    <form method="POST" action="{{ route('accounting.transfers.store') }}" data-transfer-form>
        @csrf
        <x-card :title="__('Money moved')" icon="bi-arrow-left-right">
            <div class="row">
                <div class="col-md-3"><x-form.date name="transfer_date" :label="__('Date')" :value="old('transfer_date', now()->toDateString())" required /></div>
                <div class="col-md-3"><x-form.select name="from_bank_account_id" :label="__('From')" :options="$banks" :value="old('from_bank_account_id')" :placeholder="__('Choose an account')" required /></div>
                <div class="col-md-3"><x-form.select name="to_bank_account_id" :label="__('To')" :options="$banks" :value="old('to_bank_account_id')" :placeholder="__('Choose an account')" required /></div>
                <div class="col-md-3"><x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="old('amount')" required /></div>
            </div>
            <div class="row">
                <div class="col-md-4"><x-form.input name="reference" :label="__('Reference (slip or cheque number)')" :value="old('reference')" /></div>
                <div class="col-md-8"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
            </div>
        </x-card>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" data-post-transfer><i class="bi bi-check2-circle"></i> {{ __('Save and post') }}</button>
            <a href="{{ route('accounting.transfers.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts::app>
