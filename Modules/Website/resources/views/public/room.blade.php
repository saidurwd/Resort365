@extends('website::public.layout')

@section('title', $unit['name'])
@section('description', $unit['headline'] ?: \Illuminate\Support\Str::limit($unit['description'], 160))
@section('og_image', $unit['photos'][0]['web'] ?? '')

@section('content')
    <section class="website-section pt-4">
        <div class="container">
            <nav aria-label="{{ __('Breadcrumb') }}" class="mb-3">
                <a href="{{ route('website.rooms', ['site' => $site->slug]) }}"><i class="bi bi-arrow-left"></i> {{ __('Rooms & cottages') }}</a>
            </nav>
            <div class="row g-4">
                <div class="col-lg-7">
                    @if ($unit['photos'] !== [])
                        <div id="unit-photos" class="carousel slide website-gallery-carousel" data-photos="{{ count($unit['photos']) }}">
                            <div class="carousel-inner">
                                @foreach ($unit['photos'] as $photo)
                                    <div @class(['carousel-item', 'active' => $loop->first])>
                                        <img src="{{ $photo['web'] }}" class="d-block w-100" alt="{{ $unit['name'] }}" @if (! $loop->first) loading="lazy" @endif>
                                    </div>
                                @endforeach
                            </div>
                            @if (count($unit['photos']) > 1)
                                <button class="carousel-control-prev" type="button" data-bs-target="#unit-photos" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('Previous') }}</span></button>
                                <button class="carousel-control-next" type="button" data-bs-target="#unit-photos" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">{{ __('Next') }}</span></button>
                            @endif
                        </div>
                    @else
                        <div class="website-card-placeholder website-card-placeholder-lg"><i class="bi bi-house-heart"></i></div>
                    @endif
                </div>
                <div class="col-lg-5">
                    <h1 class="h2">{{ $unit['name'] }}</h1>
                    @if ($unit['headline'])
                        <p class="lead">{{ $unit['headline'] }}</p>
                    @endif
                    @include('website::public.partials.price', ['from' => $unit['from']])
                    <ul class="website-facts list-unstyled my-4" data-facts>
                        <li><i class="bi bi-people"></i> {{ trans_choice('{1} Sleeps 1|[2,*] Sleeps :count', $unit['max_occupancy'], ['count' => $unit['max_occupancy']]) }}
                            @if ($unit['max_adults'])
                                ({{ trans_choice('{1} up to 1 adult|[2,*] up to :count adults', $unit['max_adults'], ['count' => $unit['max_adults']]) }}@if ($unit['max_children']), {{ trans_choice('{1} 1 child|[2,*] :count children', $unit['max_children'], ['count' => $unit['max_children']]) }}@endif)
                            @endif
                        </li>
                        @if ($unit['slug'] === 'cottage')
                            <li><i class="bi bi-door-closed"></i> {{ trans_choice('{1} 1 bedroom|[2,*] :count bedrooms', $unit['bedrooms'], ['count' => $unit['bedrooms']]) }} · {{ __('the whole cottage is yours') }}</li>
                        @endif
                        @if ($unit['bed_configuration'])
                            <li><i class="bi bi-moon-stars"></i> {{ $unit['bed_configuration'] }}</li>
                        @endif
                        @if ($unit['size_sqm'])
                            <li><i class="bi bi-arrows-fullscreen"></i> {{ __(':size m²', ['size' => \Modules\Website\Services\Prices::number($unit['size_sqm'])]) }}</li>
                        @endif
                    </ul>
                    @if ($site->phone)
                        <a class="btn btn-website-primary btn-lg w-100" href="tel:{{ preg_replace('/[^\d+]/', '', $site->phone) }}"><i class="bi bi-telephone"></i> {{ __('Call to book') }}</a>
                    @endif
                </div>
            </div>

            @if ($unit['description'])
                <div class="website-narrow mt-5">
                    <h2 class="h4">{{ __('About this stay') }}</h2>
                    <p>{!! nl2br(e($unit['description'])) !!}</p>
                </div>
            @endif

            @if ($unit['amenities'] !== [])
                <div class="website-narrow mt-4">
                    <h2 class="h4">{{ __('Amenities') }}</h2>
                    <ul class="website-amenities list-unstyled" data-amenities>
                        @foreach ($unit['amenities'] as $amenity)
                            <li><i class="bi {{ $amenity['icon'] ?: 'bi-check2' }}"></i> {{ $amenity['name'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endsection
