@foreach ($amenities as $amenity)
    <span class="badge text-bg-light border me-1 mb-1">@if ($amenity->icon)<i class="bi {{ $amenity->icon }}"></i> @endif{{ $amenity->name }}</span>
@endforeach
