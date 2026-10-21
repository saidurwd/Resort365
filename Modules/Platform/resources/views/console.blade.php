<x-layouts::platform :title="__('Overview')" :breadcrumbs="[__('Platform') => null, __('Overview') => null]">
    <div class="row" data-platform-counts>
        <div class="col-6 col-lg-3"><x-stat-box :value="$total" :label="__('Tenants')" icon="bi-buildings" color="primary" :url="route('platform.tenants.index')" data-count="total" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="$counts['active']" :label="__('Active')" icon="bi-check-circle" color="success" data-count="active" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="$counts['trial']" :label="__('On trial')" icon="bi-hourglass-split" color="info" data-count="trial" /></div>
        <div class="col-6 col-lg-3"><x-stat-box :value="$counts['suspended']" :label="__('Suspended')" icon="bi-pause-circle" color="warning" data-count="suspended" /></div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <x-card :title="__('Tenants by plan')" icon="bi-box-seam" body-class="p-0">
                <table class="table table-sm mb-0" data-by-plan>
                    <tbody>
                        @foreach ($plans as $row)
                            <tr><td class="ps-3"><a href="{{ route('platform.plans.edit', $row['plan']) }}">{{ $row['plan']->name }}</a></td><td class="text-end pe-3">{{ $row['tenants'] }}</td></tr>
                        @endforeach
                        <tr><td class="ps-3 text-body-secondary">{{ __('No plan') }}</td><td class="text-end pe-3">{{ $noPlan }}</td></tr>
                    </tbody>
                </table>
            </x-card>
            <x-card :title="__('Trials ending within two weeks')" icon="bi-hourglass-split" body-class="p-0">
                <table class="table table-sm mb-0" data-trials>
                    <tbody>
                        @forelse ($trials as $tenant)
                            <tr><td class="ps-3"><a href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td><td class="text-end pe-3">{{ $tenant->trial_ends_at?->format('d M') }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('None.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-lg-8">
            <x-card :title="__('Newest tenants')" icon="bi-stars" body-class="p-0">
                <table class="table table-sm mb-0" data-newest>
                    <tbody>
                        @foreach ($newest as $tenant)
                            <tr><td class="ps-3"><a href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a> <span class="text-body-secondary small">{{ $tenant->slug }}</span></td><td><x-status-badge :status="$tenant->status" /></td><td class="text-end pe-3">{{ $tenant->created_at?->format('d M Y') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            <x-card :title="__('Latest in the audit log')" icon="bi-journal-text" body-class="p-0">
                <table class="table table-sm mb-0" data-recent>
                    <tbody>
                        @forelse ($recent as $entry)
                            <tr><td class="ps-3">{{ $entry->summary }}</td><td class="text-body-secondary small">{{ $entry->admin_name }}</td><td class="text-end pe-3 small">{{ $entry->created_at->format('d M H:i') }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts::platform>
