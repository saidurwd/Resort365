<x-layouts::platform :title="__('Tenants')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Tenants') => null]">
    <form method="GET" class="d-flex gap-2 mb-3" data-tenant-search>
        <input type="search" name="q" value="{{ $search }}" class="form-control w-auto" placeholder="{{ __('Name or subdomain') }}">
        <button type="submit" class="btn btn-outline-primary">{{ __('Search') }}</button>
    </form>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-tenants>
                <thead><tr><th class="ps-3">{{ __('Tenant') }}</th><th>{{ __('Status') }}</th><th>{{ __('Plan') }}</th>@foreach (['properties', 'rooms', 'users', 'storage'] as $meter)<th class="text-end">{{ __(ucfirst($meter)) }}</th>@endforeach<th class="pe-3">{{ __('Since') }}</th></tr></thead>
                <tbody>
                    @forelse ($tenants as $tenant)
                        <tr data-tenant="{{ $tenant->slug }}">
                            <td class="ps-3"><a href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a><div class="small text-body-secondary">{{ $tenant->domain() }}</div></td>
                            <td><x-status-badge :status="$tenant->status" /></td>
                            <td>{{ $plans->get($tenant->plan_id)?->name ?? '—' }}</td>
                            @foreach (['properties', 'rooms', 'users', 'storage'] as $meter)
                                @php($row = $usage[$tenant->id][$meter] ?? null)
                                <td class="text-end font-monospace" data-usage="{{ $meter }}">{{ $row ? number_format((float) $row['used'], $row['unit'] === 'GB' ? 2 : 0) : '—' }}@if ($row && $row['limit'] !== null)<span class="text-body-secondary"> / {{ number_format((float) $row['limit'], 0) }}</span>@endif</td>
                            @endforeach
                            <td class="pe-3">{{ $tenant->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty-state :title="__('No tenants found')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::platform>
