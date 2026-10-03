<x-layouts::app :title="__('Companies')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Companies') => null]">
    @can('create', \Modules\Guest\Models\Company::class)
        <x-slot:actions>
            <a href="{{ route('guest.companies.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New company') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($records->isEmpty())
            <x-empty-state icon="bi-building" :title="__('No companies yet')" :message="__('Corporate clients that book or pay for guests.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-companies>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Company') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th>{{ __('Tax number') }}</th>
                            <th class="text-end">{{ __('Credit limit (:currency)', ['currency' => $currency]) }}</th>
                            <th class="text-end">{{ __('Terms') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $company)
                            <tr>
                                <td class="ps-3"><div class="fw-semibold">{{ $company->name }}</div><div class="small text-body-secondary">{{ $company->legal_name }}</div></td>
                                <td>{{ $company->contact_person }}<div class="small text-body-secondary">{{ collect([$company->phone, $company->email])->filter()->implode(' · ') }}</div></td>
                                <td>{{ $company->tax_number ?? '—' }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $company->credit_limit, 2) }}</td>
                                <td class="text-end">{{ $company->payment_terms_days ? trans_choice(':count day|:count days', $company->payment_terms_days) : __('Cash') }}</td>
                                <td>@include('guest::partials.active-badge', ['active' => $company->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $company)
                                        <a href="{{ route('guest.companies.edit', $company) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $company)
                                        <x-confirm-delete :action="route('guest.companies.destroy', $company)" icon-only :title="__('Delete company :name?', ['name' => $company->name])" />
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
