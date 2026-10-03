{{-- Row actions for a room (rooms DataTable and the cottage page). --}}
@can('update', $room)
    <a href="{{ route('property.rooms.edit', $room) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit room :number', ['number' => $room->number]) }}"><i class="bi bi-pencil"></i></a>
@endcan
@can('delete', $room)
    <x-confirm-delete :action="route('property.rooms.destroy', $room)" icon-only :title="__('Delete room :number?', ['number' => $room->number])" :text="__('A room with future bookings cannot be deleted; deactivate it instead.')" />
@endcan
