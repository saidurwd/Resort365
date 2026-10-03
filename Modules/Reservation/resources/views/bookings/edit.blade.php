<x-layouts::app :title="__('Change stay')" :subtitle="$reservation->code" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => route('reservation.bookings.index'), $reservation->code => route('reservation.bookings.show', $reservation), __('Change stay') => null]">
    <form method="POST" action="{{ route('reservation.bookings.review', $reservation) }}" data-modify-form>
        @csrf
        <x-card :title="__('Dates')" icon="bi-calendar-range">
            <div class="row">
                <x-form.date name="check_in" :label="__('Check-in')" :value="$reservation->check_in->toDateString()" wrapper-class="col-sm-6 col-lg-3" required />
                <x-form.date name="check_out" :label="__('Check-out')" :value="$reservation->check_out->toDateString()" wrapper-class="col-sm-6 col-lg-3" required />
            </div>
        </x-card>

        <x-card :title="__('Rooms and cottages')" icon="bi-houses">
            @error('items')
                <div class="alert alert-danger" data-modify-error>{{ $message }}</div>
            @enderror
            <p class="small text-body-secondary">{{ __('Change a row, tick Remove to drop it, or fill the empty row to add a room or cottage. Rooms are checked when you save: if another booking holds them, nothing changes.') }}</p>
            @foreach ($rows as $index => $row)
                <div class="row g-2 align-items-end border-bottom pb-2 mb-2" data-item-row>
                    <x-form.select name="items[{{ $index }}][unit]" :label="__('Room or cottage')" :options="$units" :value="$row['unit'] ?? ''" wrapper-class="col-lg-4 mb-0" :placeholder="__('— add a room or cottage —')" />
                    <x-form.select name="items[{{ $index }}][rate_plan]" :label="__('Rate plan')" :options="$plans" :value="$row['rate_plan'] ?? ''" :search="false" wrapper-class="col-lg-3 mb-0" />
                    <x-form.input name="items[{{ $index }}][adults]" type="number" :label="__('Adults')" :value="$row['adults'] ?? 2" min="1" max="50" wrapper-class="col-4 col-lg-1 mb-0" />
                    <x-form.input name="items[{{ $index }}][children]" type="number" :label="__('Children')" :value="$row['children'] ?? 0" min="0" max="30" wrapper-class="col-4 col-lg-1 mb-0" />
                    <div class="col-4 col-lg-2 pb-2">
                        @if (($row['unit'] ?? '') !== '')
                            <div class="form-check">
                                <input type="hidden" name="items[{{ $index }}][remove]" value="0">
                                <input class="form-check-input" type="checkbox" name="items[{{ $index }}][remove]" value="1" id="remove-{{ $index }}" @checked(filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN))>
                                <label class="form-check-label" for="remove-{{ $index }}">{{ __('Remove') }}</label>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </x-card>

        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-calculator"></i> {{ __('Review the new price') }}</button>
            <a href="{{ route('reservation.bookings.show', $reservation) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
    </form>
</x-layouts::app>
