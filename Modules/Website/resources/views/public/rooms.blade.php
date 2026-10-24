@extends('website::public.layout')

@section('title', __('Rooms & cottages'))

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Rooms & cottages') }}</h1>
            <p class="lead mb-0">{{ __('Choose a room, or take a whole cottage for your family or friends.') }}</p>
        </div>
    </section>
    <section class="website-section">
        <div class="container">
            @if ($units === [])
                <p class="text-center text-body-secondary">{{ __('Our rooms will be listed here soon.') }}</p>
            @else
                <div class="row g-4" data-units>
                    @foreach ($units as $unit)
                        <div class="col-sm-6 col-lg-4">@include('website::public.partials.unit-card')</div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
