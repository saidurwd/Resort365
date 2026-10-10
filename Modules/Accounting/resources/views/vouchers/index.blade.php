<x-layouts::app :title="__('Vouchers')" :subtitle="__('Quick income and expense vouchers')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Vouchers') => null]">
    <x-slot:actions>
        @if ($canCreate)
            <a href="{{ route('accounting.vouchers.create', 'income') }}" class="btn btn-success" data-new-income><i class="bi bi-plus-lg"></i> {{ __('Income voucher') }}</a>
            <a href="{{ route('accounting.vouchers.create', 'expense') }}" class="btn btn-danger" data-new-expense><i class="bi bi-plus-lg"></i> {{ __('Expense voucher') }}</a>
        @endif
    </x-slot:actions>

    <form method="GET" action="{{ route('accounting.vouchers.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <x-form.select name="type" :label="__('Type')" :options="$types" :value="request('type')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="vouchers" :url="route('accounting.vouchers.data', request()->only(['type']))" :columns="$columns" :order="[[1, 'desc']]" :empty-text="__('No vouchers yet.')" />
    </x-card>
</x-layouts::app>
