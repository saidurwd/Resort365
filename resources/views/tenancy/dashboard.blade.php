@php($tenant = app(\App\Support\Tenancy\TenantContext::class)->tenantOrFail())

{{-- TODO(step-7.1): replaced by the management dashboard. --}}
<x-layouts::app :title="__('Dashboard')">
    <x-card>
        <x-empty-state icon="bi-speedometer2" :title="__('Welcome to :name', ['name' => $tenant->name])" :message="__('You are signed in as :name. Modules will add their dashboards here.', ['name' => auth()->user()?->getAttribute('name')])">
            <x-status-badge :status="$tenant->status" />
        </x-empty-state>
    </x-card>
</x-layouts::app>
