<x-card :title="__('Front desk')" icon="bi-door-open">
    @switch ($reservation->status)
        @case(\Modules\Reservation\Enums\ReservationStatus::CheckedIn)
            <p class="text-success" data-stay="in-house"><i class="bi bi-house-check"></i> {{ __('In house until :date.', ['date' => \Carbon\Carbon::parse($reservation->checkOut)->format('D d M Y')]) }}</p>
            @break
        @case(\Modules\Reservation\Enums\ReservationStatus::Confirmed)
        @case(\Modules\Reservation\Enums\ReservationStatus::Tentative)
            <p data-stay="expected">{{ __('Arrives :date.', ['date' => \Carbon\Carbon::parse($reservation->checkIn)->format('D d M Y')]) }}</p>
            @can('frontoffice.checkin.perform')
                <a href="{{ route('frontoffice.check-in.show', $reservation->id) }}" class="btn btn-success" data-tab-check-in><i class="bi bi-box-arrow-in-right"></i> {{ __('Check in') }}</a>
            @endcan
            @break
        @default
            <p class="text-body-secondary">{{ $reservation->status->label() }}</p>
    @endswitch
    @can('frontoffice.checkin.perform')
        <a href="{{ route('frontoffice.check-in.card', $reservation->id) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> {{ __('Registration card') }}</a>
    @endcan
</x-card>
