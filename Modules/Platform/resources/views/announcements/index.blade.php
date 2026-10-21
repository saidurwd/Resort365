<x-layouts::platform :title="__('Announcements')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Announcements') => null]">
    <x-slot:actions><a href="{{ route('platform.announcements.create') }}" class="btn btn-sm btn-primary" data-new-announcement><i class="bi bi-plus-lg"></i> {{ __('New announcement') }}</a></x-slot:actions>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-announcements>
            <thead><tr><th class="ps-3">{{ __('Announcement') }}</th><th>{{ __('Level') }}</th><th>{{ __('For') }}</th><th>{{ __('Runs') }}</th><th class="pe-3"></th></tr></thead>
            <tbody>
                @forelse ($announcements as $announcement)
                    <tr data-announcement="{{ $announcement->id }}">
                        <td class="ps-3"><a href="{{ route('platform.announcements.edit', $announcement) }}">{{ $announcement->title }}</a><div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($announcement->body, 90) }}</div></td>
                        <td><x-status-badge :status="$announcement->level" /></td>
                        <td>{{ $announcement->audience->label() }}</td>
                        <td class="small">{{ $announcement->starts_at->format('d M H:i') }} → {{ $announcement->ends_at?->format('d M H:i') ?? __('until deleted') }} @if ($announcement->isActive()) <span class="badge text-bg-success">{{ __('showing') }}</span> @endif</td>
                        <td class="text-end pe-3"><form method="POST" action="{{ route('platform.announcements.destroy', $announcement) }}" class="d-inline" data-confirm="{{ __('Delete this announcement?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-delete-announcement><i class="bi bi-trash"></i></button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No announcements')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::platform>
