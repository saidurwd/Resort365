<x-layouts::app :title="__('Table reservations')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Table reservations') => null]">
    @if (! $outlet)
        <x-empty-state icon="bi-calendar2-event" :title="__('No outlets for you')" :message="__('You do not work in any outlet of this property.')" />
    @else
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-reservation-filter>
            <x-form.select name="outlet" :label="__('Outlet')" :options="$outlets->pluck('name', 'id')->all()" :value="$outlet->id" :search="false" wrapper-class="mb-0" />
            <x-form.input name="date" type="date" :label="__('Date')" :value="$date" wrapper-class="mb-0" />
            <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
        </form>

        <div class="row">
            <div class="col-xl-7">
                <x-card :title="__('Bookings on :date', ['date' => \Illuminate\Support\Carbon::parse($date)->format('D d M')])" icon="bi-calendar2-event" body-class="p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th class="ps-3">{{ __('Time') }}</th><th>{{ __('Guest') }}</th><th>{{ __('Party') }}</th><th>{{ __('Table') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($reservations as $reservation)
                                    <tr data-reservation-row="{{ $reservation->customer_name }}">
                                        <td class="ps-3 text-nowrap">{{ $reservation->reserved_for->copy()->setTimezone($timezone)->format('H:i') }}</td>
                                        <td>{{ $reservation->customer_name }}@if ($reservation->reservation_id) <span class="badge text-bg-info">{{ __('In house') }}</span>@endif
                                            <div class="small text-body-secondary">{{ collect([$reservation->phone, $reservation->occasion, $reservation->notes])->filter()->implode(' · ') }}</div></td>
                                        <td>{{ $reservation->party_size }}</td>
                                        <td>{{ $reservation->table?->number ?? '—' }}</td>
                                        <td><x-status-badge :status="$reservation->status" /></td>
                                        <td class="text-end pe-3 text-nowrap">
                                            @if ($canManage && $reservation->status === \Modules\Restaurant\Enums\TableReservationStatus::Booked)
                                                <a href="{{ route('restaurant.reservations.index', ['outlet' => $outlet->id, 'date' => $date, 'edit' => $reservation->id]) }}" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Change') }}"><i class="bi bi-pencil"></i></a>
                                                <form method="POST" action="{{ route('restaurant.reservations.close', $reservation) }}" class="d-inline" data-confirm="{{ __('Cancel this reservation?') }}" data-confirm-variant="danger">@csrf<input type="hidden" name="status" value="cancelled"><button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Cancel') }}"><i class="bi bi-x-lg"></i></button></form>
                                                <form method="POST" action="{{ route('restaurant.reservations.close', $reservation) }}" class="d-inline">@csrf<input type="hidden" name="status" value="no_show"><button class="btn btn-sm btn-outline-warning">{{ __('No-show') }}</button></form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6"><x-empty-state icon="bi-calendar2-event" :title="__('No bookings')" :message="__('Nothing is booked for this day yet.')" /></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
            @if ($canManage)
                <div class="col-xl-5">
                    @php($default = $editing ? ['date' => $editing->reserved_for->copy()->setTimezone($timezone)->format('Y-m-d'), 'time' => $editing->reserved_for->copy()->setTimezone($timezone)->format('H:i')] : ['date' => $date, 'time' => '19:30'])
                    <x-card :title="$editing ? __('Change booking') : __('Book a table')" icon="bi-plus-lg">
                        <form method="POST" action="{{ $editing ? route('restaurant.reservations.update', $editing) : route('restaurant.reservations.store') }}" data-reservation-form>
                            @csrf
                            @if ($editing) @method('PUT') @endif
                            <input type="hidden" name="outlet_id" value="{{ $outlet->id }}">
                            <div class="row">
                                <div class="col-6"><x-form.input name="date" type="date" :label="__('Date')" :value="$default['date']" required /></div>
                                <div class="col-3"><x-form.input name="time" type="time" :label="__('Time')" :value="$default['time']" required /></div>
                                <div class="col-3"><x-form.input name="party_size" type="number" :label="__('Party')" :value="$editing?->party_size ?? 2" min="1" required /></div>
                            </div>
                            <x-form.select name="dining_table_id" :label="__('Table')" :options="$tables->mapWithKeys(fn ($table) => [$table->id => $table->number.' ('.trans_choice(':count seat|:count seats', $table->seats).')'])->all()"
                                :value="$editing?->dining_table_id" :placeholder="__('Choose later')" />
                            <x-form.select name="reservation_id" :label="__('In-house guest')" :options="collect($stays)->mapWithKeys(fn ($stay) => [$stay->reservationId => implode(', ', $stay->rooms).' · '.$stay->guestName])->all()"
                                :value="$editing?->reservation_id" :placeholder="__('Outside customer')" />
                            <x-form.input name="customer_name" :label="__('Customer name')" :value="$editing && ! $editing->reservation_id ? $editing->customer_name : ''" help="{{ __('For an outside customer.') }}" />
                            <x-form.input name="phone" :label="__('Phone')" :value="$editing?->phone" />
                            <x-form.input name="occasion" :label="__('Occasion')" :value="$editing?->occasion" placeholder="{{ __('Birthday, anniversary…') }}" />
                            <x-form.input name="notes" :label="__('Notes')" :value="$editing?->notes" placeholder="{{ __('Allergies, high chair…') }}" />
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ $editing ? __('Save') : __('Book') }}</button>
                            @if ($editing)<a href="{{ route('restaurant.reservations.index', ['outlet' => $outlet->id, 'date' => $date]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>@endif
                        </form>
                    </x-card>
                </div>
            @endif
        </div>
    @endif
</x-layouts::app>
