@php
    $tonight = \Carbon\CarbonImmutable::parse($property->businessDate);
    $departure = \Carbon\CarbonImmutable::parse($reservation->checkOut);
@endphp

<x-layouts::app :title="__('Stay changes')" :subtitle="($reservation->groupName ?? $reservation->guestName).' · '.$reservation->code"
    :breadcrumbs="[__('Front desk') => route('frontoffice.desk'), $reservation->code => route('reservation.bookings.show', $reservation->id), __('Stay changes') => null]">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <x-status-badge :status="$reservation->status" />
        <span class="text-body-secondary">{{ \Carbon\Carbon::parse($reservation->checkIn)->format('D d M') }} → {{ $departure->format('D d M Y') }} · {{ implode(', ', $reservation->units) }}</span>
    </div>

    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Move a room')" icon="bi-arrow-left-right" body-class="p-0">
                <p class="small text-body-secondary px-3 pt-3">{{ __('The remaining nights, from tonight, move to the new room; the old room is free from tonight.') }}</p>
                <table class="table align-middle mb-0" data-move-rooms>
                    <tbody>
                        @foreach ($items as $itemId => $item)
                            <tr>
                                <td class="ps-3">{{ $item['label'] }} <x-status-badge :status="$item['status']" /></td>
                                <td class="pe-3">
                                    @if ($item['room_id'] !== null && $item['status'] === \Modules\Reservation\Enums\ReservationStatus::CheckedIn)
                                        <form method="POST" action="{{ route('frontoffice.stay.move', $reservation->id) }}" class="d-flex flex-wrap gap-2 justify-content-end" data-move="{{ $itemId }}">
                                            @csrf
                                            <input type="hidden" name="reservation_item_id" value="{{ $itemId }}">
                                            <select name="room_id" class="form-select form-select-sm w-auto" aria-label="{{ __('New room') }}">
                                                @foreach ($rooms as $id => $label)@if ($id !== $item['room_id'])<option value="{{ $id }}">{{ $label }}</option>@endif @endforeach
                                            </select>
                                            @can('frontoffice.stay.reprice')
                                                <div class="form-check form-check-inline small mb-0 align-self-center">
                                                    <input type="hidden" name="reprice" value="0">
                                                    <input class="form-check-input" type="checkbox" name="reprice" value="1" id="reprice-{{ $itemId }}">
                                                    <label class="form-check-label" for="reprice-{{ $itemId }}">{{ __('charge the new room\'s rate') }}</label>
                                                </div>
                                            @endcan
                                            <button class="btn btn-sm btn-primary">{{ __('Move') }}</button>
                                        </form>
                                    @elseif ($item['room_id'] === null)
                                        <span class="small text-body-secondary">{{ __('A whole cottage is not moved here.') }}</span>
                                    @else
                                        <span class="small text-body-secondary">{{ __('Not checked in yet.') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Extend the stay')" icon="bi-calendar-plus">
                <form method="POST" action="{{ route('frontoffice.stay.extend', $reservation->id) }}" data-extend>
                    @csrf
                    <x-form.date name="check_out" :id="'extend-date'" :label="__('New departure')" :value="$departure->addDay()->toDateString()" :min="$departure->addDay()->toDateString()" required />
                    <button class="btn btn-outline-primary"><i class="bi bi-calendar-plus"></i> {{ __('Extend') }}</button>
                </form>
            </x-card>
            <x-card :title="__('Leave early')" icon="bi-calendar-minus">
                <form method="POST" action="{{ route('frontoffice.stay.shorten', $reservation->id) }}" data-shorten>
                    @csrf
                    <x-form.date name="check_out" :id="'shorten-date'" :label="__('New departure')" :value="$tonight->max(\Carbon\CarbonImmutable::parse($reservation->checkIn)->addDay())->toDateString()" required
                        :help="__('The nights after it are removed from the bill and the rooms released. Then check out.')" />
                    <button class="btn btn-outline-warning"><i class="bi bi-calendar-minus"></i> {{ __('Leave early') }}</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts::app>
