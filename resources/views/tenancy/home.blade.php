@php($tenant = app(\App\Support\Tenancy\TenantContext::class)->tenantOrFail())

{{-- TODO(step-0.5): replaced by the sign-in page and dashboard. --}}
<x-layouts::app :title="$tenant->name">
    <x-card>
        <x-empty-state icon="bi-building" :title="__('Welcome to :name', ['name' => $tenant->name])" :message="__('This is the :name workspace at :domain.', ['name' => $tenant->name, 'domain' => $tenant->domain()])">
            <x-status-badge :status="$tenant->status" />
        </x-empty-state>
    </x-card>
</x-layouts::app>
