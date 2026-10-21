<x-layouts::platform :title="__('Audit log')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Audit log') => null]">
    <form method="GET" class="d-flex gap-2 mb-3" data-audit-filter>
        <input type="text" name="action" value="{{ $action }}" class="form-control w-auto" placeholder="{{ __('Action, e.g. tenant or impersonation') }}">
        @if ($tenant) <input type="hidden" name="tenant" value="{{ $tenant }}"> @endif
        <button type="submit" class="btn btn-outline-primary">{{ __('Filter') }}</button>
    </form>
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-audit>
            <thead><tr><th class="ps-3">{{ __('When') }}</th><th>{{ __('Admin') }}</th><th>{{ __('What') }}</th><th class="pe-3">{{ __('From') }}</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr data-entry="{{ $entry->action }}">
                        <td class="ps-3 small">{{ $entry->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $entry->admin_name }}</td>
                        <td>{{ $entry->summary }}@if (! empty($entry->details['reason']))<div class="small text-body-secondary">{{ $entry->details['reason'] }}</div>@endif</td>
                        <td class="pe-3 small font-monospace">{{ $entry->ip }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state :title="__('Nothing recorded')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
    <div class="mt-3">{{ $entries->links() }}</div>
</x-layouts::platform>
