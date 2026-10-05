<x-layouts::app :title="__('POS sessions')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('POS sessions') => null]">
    <x-card body-class="p-0">
        <x-datatable id="pos-sessions" :url="route('restaurant.sessions.data')" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('No POS sessions yet.')" />
    </x-card>
</x-layouts::app>
