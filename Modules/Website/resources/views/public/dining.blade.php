@extends('website::public.layout')

@section('title', __('Dining'))

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Dining') }}</h1>
            @if ($site->translated('dining_intro'))
                <p class="lead mb-0">{!! nl2br(e($site->translated('dining_intro'))) !!}</p>
            @endif
        </div>
    </section>
    <section class="website-section">
        <div class="container">
            @if ($outlets === [])
                <p class="text-center text-body-secondary">{{ __('Ask us about meals during your stay.') }}</p>
            @else
                <div class="row g-4" data-outlets>
                    @foreach ($outlets as $outlet)
                        <div class="col-md-6 col-lg-4">
                            <div class="website-outlet h-100" data-outlet="{{ $outlet['name'] }}">
                                <h2 class="h5 mb-0">{{ $outlet['name'] }}</h2>
                                <p class="small text-body-secondary">{{ $outlet['type'] }}</p>
                                @if ($outlet['hours'] !== [])
                                    <table class="table table-sm mb-0">
                                        <tbody>
                                            @foreach ($outlet['hours'] as $hours)
                                                <tr><td>{{ $hours['day'] }}</td><td class="text-end">{{ $hours['time'] }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
