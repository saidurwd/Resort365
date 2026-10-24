@extends('website::public.layout')

@section('og_image', $heroPhotos[0]['web'] ?? '')

@section('content')
    <section class="website-hero" data-hero>
        @if ($heroPhotos !== [])
            <div id="website-hero-carousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
                <div class="carousel-inner">
                    @foreach ($heroPhotos as $photo)
                        <div @class(['carousel-item', 'active' => $loop->first])>
                            <img src="{{ $photo['web'] }}" class="website-hero-image" alt="{{ $photo['alt'] }}" @if (! $loop->first) loading="lazy" @endif>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="website-hero-plain"></div>
        @endif
        <div class="website-hero-text">
            <div class="container">
                <h1 class="display-5 fw-semibold">{{ $site->name }}</h1>
                @if ($site->translated('tagline'))
                    <p class="lead mb-4">{{ $site->translated('tagline') }}</p>
                @endif
                <a class="btn btn-website-primary btn-lg" href="{{ route('website.rooms', ['site' => $site->slug]) }}">{{ __('See rooms & cottages') }}</a>
            </div>
        </div>
    </section>

    @if ($site->translated('intro_title') || $site->translated('intro_body'))
        <section class="website-section">
            <div class="container website-narrow text-center">
                @if ($site->translated('intro_title'))
                    <h2 class="website-section-title">{{ $site->translated('intro_title') }}</h2>
                @endif
                @if ($site->translated('intro_body'))
                    <p class="website-lead">{!! nl2br(e($site->translated('intro_body'))) !!}</p>
                @endif
                @if ($site->lines('highlights') !== [])
                    <ul class="website-highlights list-unstyled" data-highlights>
                        @foreach ($site->lines('highlights') as $highlight)
                            <li><i class="bi bi-check2-circle"></i> {{ $highlight }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif

    @if ($featured !== [])
        <section class="website-section website-section-alt">
            <div class="container">
                <div class="d-flex justify-content-between align-items-end mb-4">
                    <h2 class="website-section-title mb-0">{{ __('Stay with us') }}</h2>
                    <a href="{{ route('website.rooms', ['site' => $site->slug]) }}">{{ __('All rooms & cottages') }} <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="row g-4">
                    @foreach ($featured as $unit)
                        <div class="col-sm-6 col-lg-4">@include('website::public.partials.unit-card')</div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($offers !== [])
        <section class="website-section">
            <div class="container">
                <h2 class="website-section-title">{{ __('Special offers') }}</h2>
                <div class="row g-4">
                    @foreach ($offers as $offer)
                        <div class="col-md-4">
                            <div class="website-offer h-100">
                                <div class="website-offer-discount">{{ $offer['discount'] }}</div>
                                <h3 class="h5">{{ $offer['name'] }}</h3>
                                @if ($offer['description'])
                                    <p class="mb-0">{{ $offer['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3"><a href="{{ route('website.offers', ['site' => $site->slug]) }}">{{ __('See every offer') }} <i class="bi bi-arrow-right"></i></a></p>
            </div>
        </section>
    @endif

    @include('website::public.partials.visit')
@endsection
