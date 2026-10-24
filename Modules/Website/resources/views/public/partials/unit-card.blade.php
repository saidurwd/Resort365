{{-- A room type or cottage type as a card: $unit from SitePages, $currency, $site. --}}
@php($url = route('website.room', ['site' => $site->slug, 'kind' => $unit['slug'], 'id' => $unit['id']]))
<article class="card website-card h-100" data-unit="{{ $unit['key'] }}">
    <a href="{{ $url }}" class="website-card-photo">
        @if ($unit['photos'] !== [])
            <img src="{{ $unit['photos'][0]['thumb'] }}" alt="{{ $unit['name'] }}" loading="lazy" width="480" height="320">
        @else
            <span class="website-card-placeholder"><i class="bi bi-house-heart"></i></span>
        @endif
    </a>
    <div class="card-body d-flex flex-column">
        <h3 class="h5 card-title mb-1"><a href="{{ $url }}" class="stretched-link text-reset text-decoration-none">{{ $unit['name'] }}</a></h3>
        @if ($unit['headline'])
            <p class="website-card-headline mb-2">{{ $unit['headline'] }}</p>
        @endif
        <p class="small text-body-secondary mb-3">
            <i class="bi bi-people"></i> {{ trans_choice('{1} Sleeps 1|[2,*] Sleeps :count', $unit['max_occupancy'], ['count' => $unit['max_occupancy']]) }}
            @if ($unit['slug'] === 'cottage')
                · <i class="bi bi-door-closed"></i> {{ trans_choice('{1} 1 bedroom|[2,*] :count bedrooms', $unit['bedrooms'], ['count' => $unit['bedrooms']]) }}
            @endif
        </p>
        <div class="mt-auto">@include('website::public.partials.price', ['from' => $unit['from']])</div>
    </div>
</article>
