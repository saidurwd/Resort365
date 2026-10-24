{{-- "Plan your visit": check-in times and how to reach the resort. --}}
<section class="website-section website-section-alt" data-visit>
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-md-6">
                <h2 class="website-section-title">{{ __('Plan your visit') }}</h2>
                @if ($property)
                    <p class="mb-1"><i class="bi bi-box-arrow-in-right"></i> {{ __('Check-in from :time', ['time' => \Illuminate\Support\Str::substr($property->checkInTime, 0, 5)]) }}</p>
                    <p class="mb-3"><i class="bi bi-box-arrow-right"></i> {{ __('Check-out by :time', ['time' => \Illuminate\Support\Str::substr($property->checkOutTime, 0, 5)]) }}</p>
                @endif
                @if ($site->address ?? $property?->address)
                    <p><i class="bi bi-geo-alt"></i> {{ $site->address ?? $property?->address }}</p>
                @endif
            </div>
            <div class="col-md-6 text-md-end">
                @if ($site->map_url)
                    <a class="btn btn-outline-website me-2 mb-2" href="{{ $site->map_url }}" target="_blank" rel="noopener"><i class="bi bi-map"></i> {{ __('Open the map') }}</a>
                @endif
                <a class="btn btn-website-primary mb-2" href="{{ route('website.contact', ['site' => $site->slug]) }}"><i class="bi bi-chat-dots"></i> {{ __('Contact us') }}</a>
            </div>
        </div>
    </div>
</section>
