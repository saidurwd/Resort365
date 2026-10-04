<x-layouts::app :title="__('Charge codes')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Charge codes') => null]">
    @can('create', \Modules\Billing\Models\ChargeCode::class)
        <x-slot:actions>
            <a href="{{ route('billing.charge-codes.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New charge code') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        <p class="small text-body-secondary px-3 pt-3">{{ __('What each folio charge is, how it is taxed, and its category for routing (e.g. company pays room). Shared by all your properties.') }}</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-charge-codes>
                <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Category') }}</th><th>{{ __('Tax') }}</th><th>{{ __('Status') }}</th><th class="pe-3"></th></tr></thead>
                <tbody>
                    @foreach ($codes as $code)
                        <tr data-code="{{ $code->code }}">
                            <td class="ps-3 font-monospace fw-semibold">{{ $code->code }}</td>
                            <td>{{ $code->name }}</td>
                            <td><x-status-badge :status="$code->category" /></td>
                            <td>{{ $code->tax_category_id ? ($taxCategories[$code->tax_category_id] ?? '—') : __('No tax') }}</td>
                            <td><span @class(['badge', 'text-bg-success' => $code->is_active, 'text-bg-secondary' => ! $code->is_active])>{{ $code->is_active ? __('Active') : __('Inactive') }}</span></td>
                            <td class="text-end pe-3 text-nowrap">
                                @can('update', $code)
                                    <a href="{{ route('billing.charge-codes.edit', $code) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    <x-confirm-delete :action="route('billing.charge-codes.destroy', $code)" icon-only :title="__('Delete charge code :code?', ['code' => $code->code])" :text="__('Lines already posted keep it.')" />
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
