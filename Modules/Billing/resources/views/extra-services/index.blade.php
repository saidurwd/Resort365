<x-layouts::app :title="__('Extras')" :subtitle="app(\App\Support\Tenancy\PropertyContext::class)->currentName()" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Extras') => null]">
    @can('create', \Modules\Billing\Models\ExtraService::class)
        <x-slot:actions>
            <a href="{{ route('billing.extra-services.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New extra') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($services->isEmpty())
            <x-empty-state icon="bi-bag-plus" :title="__('No extras yet')" :message="__('Airport pickups, extra beds, laundry… posted to guests\' folios at these prices.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-extra-services>
                    <thead><tr><th class="ps-3">{{ __('Extra') }}</th><th>{{ __('Charge code') }}</th><th class="text-end">{{ __('Price') }}</th><th>{{ __('Status') }}</th><th class="pe-3"></th></tr></thead>
                    <tbody>
                        @foreach ($services as $service)
                            <tr data-extra="{{ $service->name }}">
                                <td class="ps-3">{{ $service->name }}@if ($service->unit) <span class="text-body-secondary small">/ {{ $service->unit }}</span>@endif</td>
                                <td class="font-monospace">{{ $service->chargeCode->code }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $service->unit_price, 2) }} <span class="small text-body-secondary">{{ $service->price_includes_tax ? __('incl. tax') : __('+ tax') }}</span></td>
                                <td><span @class(['badge', 'text-bg-success' => $service->is_active, 'text-bg-secondary' => ! $service->is_active])>{{ $service->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $service)
                                        <a href="{{ route('billing.extra-services.edit', $service) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                        <x-confirm-delete :action="route('billing.extra-services.destroy', $service)" icon-only :title="__('Delete extra :name?', ['name' => $service->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts::app>
