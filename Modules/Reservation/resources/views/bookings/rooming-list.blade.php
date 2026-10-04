<x-layouts::app :title="__('Rooming list')" :subtitle="$reservation->group_name ?? $reservation->code"
    :breadcrumbs="[__('Reservations') => route('reservation.bookings.index'), $reservation->code => route('reservation.bookings.show', $reservation), __('Rooming list') => null]">
    <form method="POST" action="{{ route('reservation.bookings.rooming-list.update', $reservation) }}" data-rooming-list>
        @csrf
        @method('PUT')
        <x-card body-class="p-0">
            <p class="small text-body-secondary px-3 pt-3">{{ __('Who sleeps in each room. Pick a guest profile, or type a name to create one.') }}</p>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th class="ps-3">{{ __('Room or cottage') }}</th><th>{{ __('Status') }}</th><th>{{ __('Guest') }}</th><th class="pe-3">{{ __('or new guest\'s name') }}</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr data-rooming-row="{{ $row['item']->id }}">
                                <td class="ps-3 fw-semibold">{{ $row['label'] }}</td>
                                <td><x-status-badge :status="$row['item']->status" /></td>
                                <td class="w-50">
                                    <x-form.select :name="'rows['.$row['item']->id.'][guest_id]'" :id="'guest-'.$row['item']->id" wrapper-class="mb-0" :placeholder="__('Search guests…')"
                                        :options="$row['guest'] ? [$row['guest']->id => $row['guest']->name] : []" :value="$row['guest']?->id" :tom-options="['remote' => route('guest.guests.search')]" />
                                </td>
                                <td class="pe-3"><input type="text" name="rows[{{ $row['item']->id }}][new_name]" maxlength="120" class="form-control" placeholder="{{ __('First and last name') }}" aria-label="{{ __('New guest') }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
        <div class="d-flex gap-2">
            <button class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save rooming list') }}</button>
            <a href="{{ route('reservation.bookings.show', $reservation) }}#guests" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
    </form>
</x-layouts::app>
