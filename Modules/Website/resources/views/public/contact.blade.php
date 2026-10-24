@extends('website::public.layout')

@section('title', __('Contact'))

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Contact us') }}</h1>
            <p class="lead mb-0">{{ __('We are happy to help you plan your stay.') }}</p>
        </div>
    </section>
    <section class="website-section">
        <div class="container website-narrow">
            <ul class="website-contact list-unstyled" data-contact>
                @if ($site->phone)
                    <li><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $site->phone) }}">{{ $site->phone }}</a></li>
                @endif
                @if ($site->whatsapp)
                    <li><i class="bi bi-whatsapp"></i> <a href="https://wa.me/{{ preg_replace('/\D/', '', $site->whatsapp) }}" target="_blank" rel="noopener">{{ __('WhatsApp :number', ['number' => $site->whatsapp]) }}</a></li>
                @endif
                @if ($site->email)
                    <li><i class="bi bi-envelope"></i> <a href="mailto:{{ $site->email }}">{{ $site->email }}</a></li>
                @endif
                @if ($site->address ?? $property?->address)
                    <li><i class="bi bi-geo-alt"></i> {{ $site->address ?? $property?->address }}</li>
                @endif
            </ul>
            @if ($site->map_url)
                <a class="btn btn-outline-website" href="{{ $site->map_url }}" target="_blank" rel="noopener"><i class="bi bi-map"></i> {{ __('Open the map') }}</a>
            @endif
        </div>
    </section>
    @include('website::public.partials.visit')
@endsection
