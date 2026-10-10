<x-layouts::app :title="__('Transfers')" :subtitle="__('Between cash and bank accounts')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Transfers') => null]">
    <x-slot:actions>
        @if ($canManage)
            <a href="{{ route('accounting.transfers.create') }}" class="btn btn-primary" data-new-transfer><i class="bi bi-plus-lg"></i> {{ __('New transfer') }}</a>
        @endif
    </x-slot:actions>

    <x-card body-class="p-0">
        <x-datatable id="transfers" :url="route('accounting.transfers.data')" :columns="$columns" :order="[[1, 'desc']]" :empty-text="__('No transfers yet.')" />
    </x-card>
</x-layouts::app>
