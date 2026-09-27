@props([
    'action',
    'label' => null,
    'title' => null,
    'text' => null,
    'iconOnly' => false,
])

{{-- DELETE form behind the global confirm dialog: <x-confirm-delete :action="route('property.rooms.destroy', $room)" :text="__('Room :no will be removed.', ['no' => $room->number])" /> --}}
<form method="POST" action="{{ $action }}" class="d-inline"
      data-confirm="{{ $title ?? __('Delete this record?') }}"
      data-confirm-text="{{ $text ?? __('This cannot be undone.') }}"
      data-confirm-button="{{ __('Delete') }}"
      data-confirm-cancel="{{ __('Cancel') }}"
      data-confirm-variant="danger">
    @csrf
    @method('DELETE')
    <button type="submit" {{ $attributes->class(['btn', 'btn-sm', 'btn-outline-danger']) }} @if ($iconOnly) aria-label="{{ $label ?? __('Delete') }}" @endif>
        <i class="bi bi-trash"></i>@unless ($iconOnly) {{ $label ?? __('Delete') }}@endunless
    </button>
</form>
