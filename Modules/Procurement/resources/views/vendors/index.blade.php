<x-layouts::app :title="__('Vendors')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendors') => null]">
    <x-slot:actions>
        @if ($canManage)<a href="{{ route('procurement.vendors.create') }}" class="btn btn-primary" data-new-vendor><i class="bi bi-plus-lg"></i> {{ __('Add vendor') }}</a>@endif
    </x-slot:actions>
    <x-card>
        <form method="GET" class="row g-2 align-items-end" data-vendor-filter>
            <div class="col-md-5"><label class="form-label" for="q">{{ __('Search') }}</label><input class="form-control" id="q" name="q" value="{{ $term }}" placeholder="{{ __('Name or code') }}"></div>
            <div class="col-md-3"><label class="form-label" for="status">{{ __('Status') }}</label><select class="form-select" id="status" name="status"><option value="">{{ __('All') }}</option>@foreach (\Modules\Procurement\Enums\VendorStatus::cases() as $case)<option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->label() }}</option>@endforeach</select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-outline-secondary w-100">{{ __('Filter') }}</button></div>
        </form>
    </x-card>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-vendors>
                <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Vendor') }}</th><th>{{ __('Email') }}</th><th class="text-end">{{ __('Terms') }}</th><th>{{ __('Rating') }}</th><th class="text-end">{{ __('Prices') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vendors as $vendor)
                        <tr data-vendor="{{ $vendor->code }}">
                            <td class="ps-3 font-monospace">{{ $vendor->code }}</td><td>{{ $vendor->name }}</td><td>{{ $vendor->email }}</td>
                            <td class="text-end">{{ trans_choice(':count day|:count days', $vendor->payment_terms_days) }}</td><td>{{ $vendor->rating ? str_repeat('★', $vendor->rating) : '—' }}</td>
                            <td class="text-end">{{ $vendor->prices_count }}</td><td><x-status-badge :status="$vendor->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('procurement.vendors.show', $vendor) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state :title="__('No vendors yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
