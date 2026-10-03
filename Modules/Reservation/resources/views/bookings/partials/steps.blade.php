{{-- Booking wizard progress: steps reached so far are links. --}}
@php
    $steps = [
        1 => [__('Dates & guests'), 'reservation.bookings.create'],
        2 => [__('Choose'), 'reservation.bookings.choose'],
        3 => [__('Guest'), 'reservation.bookings.guest'],
        4 => [__('Pricing & deposit'), 'reservation.bookings.pricing'],
        5 => [__('Confirm'), 'reservation.bookings.confirm'],
    ];
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-wizard-steps>
    @foreach ($steps as $number => [$label, $route])
        @if ($number === $step)
            <span class="badge text-bg-primary fs-6" aria-current="step">{{ $number }}. {{ $label }}</span>
        @elseif ($number <= $reached)
            <a href="{{ route($route) }}" class="badge text-bg-light border fs-6 text-decoration-none">{{ $number }}. {{ $label }}</a>
        @else
            <span class="badge text-bg-light border fs-6 text-body-tertiary">{{ $number }}. {{ $label }}</span>
        @endif
        @unless ($loop->last)<i class="bi bi-chevron-right text-body-tertiary"></i>@endunless
    @endforeach
    <form method="POST" action="{{ route('reservation.bookings.reset') }}" class="ms-auto">
        @csrf
        <button type="submit" class="btn btn-sm btn-link">{{ __('Start over') }}</button>
    </form>
</div>
@if (isset($summary['check_in']) && $step > 1)
    <p class="text-body-secondary small" data-wizard-summary>
        {{ \Carbon\Carbon::parse($summary['check_in'])->format('D d M Y') }} → {{ \Carbon\Carbon::parse($summary['check_out'])->format('D d M Y') }}
        · {{ trans_choice(':count night|:count nights', (int) \Carbon\Carbon::parse($summary['check_in'])->diffInDays(\Carbon\Carbon::parse($summary['check_out']))) }}
        · {{ trans_choice(':count adult|:count adults', (int) $summary['adults']) }}@if (! empty($summary['children'])), {{ trans_choice(':count child|:count children', (int) $summary['children']) }}@endif
    </p>
@endif
