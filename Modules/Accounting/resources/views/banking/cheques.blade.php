<x-layouts::app :title="__('Cheques')" :subtitle="__('Vouchers paid or received by cheque')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Cheques') => null]">
    <form method="GET" action="{{ route('accounting.cheques.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <x-form.select name="status" :label="__('Status')" :options="$statuses" :value="request('status')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="cheques" :url="route('accounting.cheques.data', request()->only(['status']))" :columns="$columns" :order="[[1, 'desc']]" :empty-text="__('No cheques yet. Enter a cheque number on an income or expense voucher.')" />
    </x-card>
</x-layouts::app>
