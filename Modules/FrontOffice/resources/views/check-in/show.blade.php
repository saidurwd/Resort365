@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $ready = in_array($reservation->status, [\Modules\Reservation\Enums\ReservationStatus::Confirmed, \Modules\Reservation\Enums\ReservationStatus::CheckedIn], true)
        && $reservation->checkIn <= $property->businessDate && $reservation->itemsCheckedIn < $reservation->itemsTotal;
@endphp

<x-layouts::app :title="__('Check in :guest', ['guest' => $reservation->guestName])" :subtitle="$reservation->code"
    :breadcrumbs="[__('Front desk') => route('frontoffice.desk'), $reservation->code => route('reservation.bookings.show', $reservation->id), __('Check in') => null]">
    <x-slot:actions>
        <a href="{{ route('frontoffice.check-in.card', $reservation->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" data-registration-card><i class="bi bi-printer"></i> {{ __('Registration card') }}</a>
    </x-slot:actions>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <x-status-badge :status="$reservation->status" />
        <x-status-badge :status="$reservation->paymentStatus" />
        <span class="text-body-secondary">{{ \Carbon\Carbon::parse($reservation->checkIn)->format('D d M') }} → {{ \Carbon\Carbon::parse($reservation->checkOut)->format('D d M Y') }} · {{ implode(', ', $reservation->units) }}</span>
    </div>

    <div class="row">
        <div class="col-xl-6">
            {{-- 1. Identity --}}
            <x-card :title="__('1. Guest ID')" icon="bi-person-vcard">
                @if ($guest?->idType)
                    <p class="text-success small" data-id-on-file><i class="bi bi-check-circle"></i> {{ __('ID on file: :type', ['type' => \Modules\Guest\Enums\IdType::from($guest->idType)->label()]) }}@if ($guest->idExpiry) · {{ __('expires :date', ['date' => \Carbon\Carbon::parse($guest->idExpiry)->format('d M Y')]) }}@endif</p>
                @endif
                <form method="POST" action="{{ route('frontoffice.check-in.identity', $reservation->id) }}" enctype="multipart/form-data" data-identity-form>
                    @csrf
                    <div class="row">
                        <div class="col-md-5"><x-form.select name="id_type" :label="__('ID type')" :options="$idTypes" :value="$guest?->idType" required :search="false" /></div>
                        <div class="col-md-7"><x-form.input name="id_number" :label="__('ID number')" required maxlength="50" /></div>
                        <div class="col-md-6"><x-form.date name="id_expiry" :label="__('Expires')" :value="$guest?->idExpiry" /></div>
                        <div class="col-md-6"><x-form.input name="nationality_code" :label="__('Nationality (code)')" :value="$guest?->nationalityCode" maxlength="2" :help="__('e.g. BD, GB')" /></div>
                    </div>
                    <x-form.input name="scan" type="file" :label="__('ID scan')" accept=".jpg,.jpeg,.png,.pdf" :help="__('Photo or PDF, up to 10 MB. Saved to the guest\'s ID documents.')" />
                    <button class="btn btn-outline-primary"><i class="bi bi-save"></i> {{ __('Save ID') }}</button>
                </form>
            </x-card>

            {{-- 2. Rooms --}}
            <x-card :title="__('2. Rooms')" icon="bi-door-open" body-class="p-0">
                <table class="table align-middle mb-0" data-rooms>
                    <tbody>
                        @foreach ($items as $itemId => $item)
                            <tr data-item="{{ $itemId }}">
                                <td class="ps-3">{{ $item['label'] }} <x-status-badge :status="$item['status']" /></td>
                                <td class="text-nowrap">
                                    @if ($ready && $item['status'] === \Modules\Reservation\Enums\ReservationStatus::Confirmed && count($items) > 1)
                                        <form method="POST" action="{{ route('frontoffice.check-in.store', $reservation->id) }}" data-check-in-item="{{ $itemId }}">
                                            @csrf
                                            <input type="hidden" name="item_id" value="{{ $itemId }}">
                                            <button class="btn btn-sm btn-success"><i class="bi bi-box-arrow-in-right"></i> {{ __('Check in') }}</button>
                                        </form>
                                    @endif
                                </td>
                                <td class="pe-3">
                                    @if ($item['room_id'] !== null && $item['status'] !== \Modules\Reservation\Enums\ReservationStatus::CheckedIn)
                                        <form method="POST" action="{{ route('frontoffice.check-in.room', $reservation->id) }}" class="d-flex gap-2 justify-content-end" data-change-room="{{ $itemId }}">
                                            @csrf
                                            <input type="hidden" name="reservation_item_id" value="{{ $itemId }}">
                                            <select name="room_id" class="form-select form-select-sm w-auto" aria-label="{{ __('Room') }}">
                                                @foreach ($roomsByType->get($item['room_type_id'], collect()) as $room)
                                                    <option value="{{ $room->id }}" @selected($room->id === $item['room_id'])>{{ __('Room :number', ['number' => $room->number]) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="btn btn-sm btn-outline-secondary">{{ __('Change') }}</button>
                                        </form>
                                    @else
                                        <span class="small text-body-secondary">{{ __('Whole cottage') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>

        <div class="col-xl-6">
            {{-- 3. Money --}}
            <x-card :title="__('3. Payment')" icon="bi-cash-coin">
                <table class="table table-sm mb-3" data-check-in-money>
                    <tr><td>{{ __('Grand total') }}</td><td class="text-end font-monospace">{{ $reservation->currencyCode }} {{ $money($reservation->grandTotal) }}</td></tr>
                    <tr><td>{{ __('Paid') }}</td><td class="text-end font-monospace">{{ $money($reservation->amountPaid) }}</td></tr>
                    <tr class="fw-semibold"><td>{{ __('Balance') }}</td><td class="text-end font-monospace" data-balance>{{ $money($reservation->balanceDue) }}</td></tr>
                </table>
                @if ($canTakePayments)
                    <form method="POST" action="{{ route('billing.payments.store') }}" class="row g-2 align-items-end" data-check-in-payment>
                        @csrf
                        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                        <input type="hidden" name="return_to" value="{{ url()->current() }}">
                        <div class="col-sm-4"><x-form.select name="method" :label="__('Method')" :search="false" :options="\Modules\Billing\Enums\PaymentMethod::options()" :value="'cash'" wrapper-class="mb-0" error-bag="payment" /></div>
                        <div class="col-sm-4"><x-form.input name="amount" type="number" step="0.01" min="0.01" :label="__('Amount')" :value="$reservation->balanceDue !== '0.00' ? $reservation->balanceDue : ''" wrapper-class="mb-0" error-bag="payment" /></div>
                        <div class="col-sm-4">
                            <div class="form-check mb-2">
                                <input type="hidden" name="security_deposit" value="0">
                                <input class="form-check-input" type="checkbox" name="security_deposit" value="1" id="security-deposit">
                                <label class="form-check-label small" for="security-deposit">{{ __('Security deposit (refundable)') }}</label>
                            </div>
                        </div>
                        <div class="col-12"><button class="btn btn-outline-success"><i class="bi bi-cash"></i> {{ __('Take payment') }}</button></div>
                    </form>
                @endif
            </x-card>

            {{-- 4. Check in --}}
            <x-card :title="__('4. Check in')" icon="bi-box-arrow-in-right">
                @if ($reservation->itemsCheckedIn >= $reservation->itemsTotal && $reservation->itemsTotal > 0)
                    <p class="text-success mb-0" data-checked-in><i class="bi bi-house-check"></i> {{ __('Checked in.') }}</p>
                @else
                    @unless ($ready)
                        <div class="alert alert-warning py-2 small" data-not-ready>
                            {{ $reservation->status === \Modules\Reservation\Enums\ReservationStatus::Tentative ? __('The booking is still tentative: take the deposit first.') : __('This booking cannot be checked in today.') }}
                        </div>
                    @endunless
                    <form method="POST" action="{{ route('frontoffice.check-in.store', $reservation->id) }}" data-check-in-form>
                        @csrf
                        <button class="btn btn-success btn-lg w-100" @disabled(! $ready)><i class="bi bi-box-arrow-in-right"></i> {{ $reservation->itemsTotal > 1 ? __('Check in all :count rooms', ['count' => $reservation->itemsTotal - $reservation->itemsCheckedIn]) : __('Check in :guest', ['guest' => $reservation->guestName]) }}</button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
