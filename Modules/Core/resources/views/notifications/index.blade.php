<x-layouts::app :title="__('Notifications')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Notifications') => null]">
    <x-slot:actions>
        <form method="POST" action="{{ route('core.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2-all"></i> {{ __('Mark all as read') }}</button>
        </form>
    </x-slot:actions>

    <x-card body-class="p-0">
        @forelse ($notifications as $notification)
            <form method="POST" action="{{ route('core.notifications.read', $notification->id) }}" class="border-bottom">
                @csrf
                <button type="submit" @class(['btn text-start w-100 d-flex gap-3 px-3 py-2 rounded-0', 'fw-semibold' => $notification->read_at === null])>
                    <i class="bi {{ $notification->data['icon'] ?? 'bi-bell' }} fs-5 text-primary"></i>
                    <span class="flex-grow-1">
                        <span class="d-block">{{ $notification->data['title'] ?? '' }}</span>
                        <span class="d-block small text-body-secondary fw-normal">{{ $notification->data['body'] ?? '' }}</span>
                    </span>
                    <span class="small text-body-secondary fw-normal text-nowrap">{{ $notification->created_at?->diffForHumans() }}</span>
                </button>
            </form>
        @empty
            <x-empty-state icon="bi-bell" :title="__('No notifications')" />
        @endforelse

        @if ($notifications->hasPages())
            <x-slot:footer>{{ $notifications->links() }}</x-slot:footer>
        @endif
    </x-card>
</x-layouts::app>
