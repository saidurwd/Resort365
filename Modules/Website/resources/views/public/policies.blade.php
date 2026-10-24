@extends('website::public.layout')

@section('title', __('Policies'))

@section('content')
    <section class="website-page-head">
        <div class="container"><h1>{{ __('Policies') }}</h1></div>
    </section>
    <section class="website-section">
        <div class="container website-narrow">
            @if ($property)
                <h2 class="h4">{{ __('Check-in and check-out') }}</h2>
                <p>{{ __('Check-in from :in; check-out by :out.', ['in' => \Illuminate\Support\Str::substr($property->checkInTime, 0, 5), 'out' => \Illuminate\Support\Str::substr($property->checkOutTime, 0, 5)]) }}</p>
            @endif

            @foreach ($policies as $policy)
                <div class="website-policy" data-policy>
                    @if (count($policies) > 1)
                        <h2 class="h4">{{ implode(', ', $policy['plans']) }}</h2>
                    @endif
                    <h3 class="h5">{{ __('Deposit and payment') }}</h3>
                    <ul>
                        @foreach ($policy['deposit'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <h3 class="h5">{{ __('Cancellation') }}</h3>
                    <ul data-cancellation>
                        @foreach ($policy['cancellation'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            @if ($site->lines('house_rules') !== [])
                <h2 class="h4">{{ __('House rules') }}</h2>
                <ul data-house-rules>
                    @foreach ($site->lines('house_rules') as $rule)
                        <li>{{ $rule }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
