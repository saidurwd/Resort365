<x-layouts::app :title="__('Cashier shifts')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Billing') => null, __('Cashier shifts') => null]">
    <x-card body-class="p-0">
        <x-datatable id="shifts-table" :url="route('billing.shifts.data')" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('No shifts yet.')" />
    </x-card>
</x-layouts::app>
