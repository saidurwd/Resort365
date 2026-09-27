<x-layouts::app :title="__('Audit log')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Audit log') => null]">
    <x-card body-class="p-3">
        <x-datatable id="audit-log" :url="route('core.audit.data')" :columns="$columns" :order="[[0, 'desc']]" :empty-text="__('No changes recorded yet.')" />
    </x-card>
</x-layouts::app>
