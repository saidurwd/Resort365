@extends('website::public.layout')

@section('title', __('Gallery'))

@section('content')
    <section class="website-page-head">
        <div class="container"><h1>{{ __('Gallery') }}</h1></div>
    </section>
    <section class="website-section">
        <div class="container">
            @if ($photos === [])
                <p class="text-center text-body-secondary">{{ __('Photos are coming soon.') }}</p>
            @else
                <div class="website-gallery" data-gallery>
                    @foreach ($photos as $photo)
                        <a href="{{ $photo['web'] }}" target="_blank" rel="noopener"><img src="{{ $photo['thumb'] }}" alt="{{ $photo['alt'] }}" loading="lazy" width="480" height="320"></a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
