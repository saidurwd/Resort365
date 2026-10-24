@extends('website::public.layout')

@section('title', __('Offers'))

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Special offers') }}</h1>
        </div>
    </section>
    <section class="website-section">
        <div class="container">
            @if ($offers === [])
                <p class="text-center text-body-secondary">{{ __('There are no special offers at the moment. Our best prices are always on this website.') }}</p>
            @else
                <div class="row g-4">
                    @foreach ($offers as $offer)
                        <div class="col-md-6">
                            <div class="website-offer h-100" data-offer="{{ $offer['name'] }}">
                                <div class="website-offer-discount">{{ $offer['discount'] }}</div>
                                <h2 class="h4">{{ $offer['name'] }}</h2>
                                @if ($offer['description'])
                                    <p>{{ $offer['description'] }}</p>
                                @endif
                                <ul class="mb-0">
                                    @foreach ($offer['conditions'] as $condition)
                                        <li>{{ $condition }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
