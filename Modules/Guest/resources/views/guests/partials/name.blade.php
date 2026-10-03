{{-- Guest name cell: link plus blacklist marker. --}}
<a href="{{ route('guest.guests.show', $guest) }}" class="fw-semibold">{{ $guest->full_name }}</a>
@if ($guest->is_blacklisted)
    <span class="badge text-bg-danger ms-1"><i class="bi bi-slash-circle"></i> {{ __('Blacklisted') }}</span>
@endif
