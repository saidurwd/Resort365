@php
    $canvas = ['width' => \Modules\Restaurant\Services\FloorPlanGeometry::WIDTH, 'height' => \Modules\Restaurant\Services\FloorPlanGeometry::HEIGHT];
@endphp
{{--
    The POS floor (ARCHITECTURE §10.3): dining areas as tabs, tables coloured by state with the running
    total and minutes seated. Alpine (posFloor) keeps it live from the outlet's channel (Step 3.5).
--}}
<x-layouts::pos :title="__('Floor')" :terminal="$terminal">
    <x-slot:status>
        @if ($businessDate)<span class="pos-bar__meta"><i class="bi bi-calendar3"></i> {{ \Illuminate\Support\Carbon::parse($businessDate)->format('D d M') }}</span>@endif
        <a href="{{ route('pos.main') }}" class="btn pos-btn btn-outline-secondary"><i class="bi bi-cash-coin"></i> {{ __('Session') }}</a>
    </x-slot:status>

    <div x-data="posIdle({{ $autoLockMinutes }})"></div>

    <div class="pos-floor" x-data="posFloor(@js(['floor' => $floor, 'channel' => $channel, 'urls' => ['floor' => route('pos.floor.data'), 'stays' => route('pos.stays')]]))" x-init="area = {{ $areas->first()->id ?? 0 }}">
        <section class="pos-card pos-floor__plan">
            @if ($areas->isEmpty())
                <x-empty-state icon="bi-grid-3x3" :title="__('No tables in this outlet')" :message="__('Use takeaway, or add dining areas and tables in the outlet setup.')" />
            @else
                <div class="d-flex flex-wrap gap-2 mb-3" role="tablist">
                    @foreach ($areas as $area)
                        <button type="button" class="btn pos-btn" :class="area === {{ $area->id }} ? 'btn-primary' : 'btn-outline-primary'" @click="area = {{ $area->id }}" data-area="{{ $area->id }}">{{ $area->name }}</button>
                    @endforeach
                    <span class="ms-auto small align-self-center" :class="online ? 'text-success' : 'text-body-secondary'" data-live>
                        <i class="bi" :class="online ? 'bi-broadcast' : 'bi-arrow-repeat'"></i> <span x-text="online ? '{{ __('Live') }}' : '{{ __('Refreshing every 5 s') }}'"></span>
                    </span>
                </div>
                @foreach ($areas as $area)
                    <svg viewBox="0 0 {{ $canvas['width'] }} {{ $canvas['height'] }}" class="floor-plan pos-floor__svg" role="img" aria-label="{{ __('Tables in :area', ['area' => $area->name]) }}" x-show="area === {{ $area->id }}" @if (! $loop->first) x-cloak @endif>
                        <rect width="{{ $canvas['width'] }}" height="{{ $canvas['height'] }}" class="floor-plan__floor" />
                        @foreach ($area->tables as $table)
                            @php
                                [$w, $h] = $table->shape->size($table->seats);
                                $now = $floor['tables'][$table->id] ?? ['status' => $table->status->value, 'subtotal' => null, 'minutes' => null];
                            @endphp
                            <g transform="translate({{ $table->pos_x }} {{ $table->pos_y }})" class="floor-table pos-table pos-table--{{ $now['status'] }}" :class="'pos-table--' + state({{ $table->id }}).status"
                                data-pos-table="{{ $table->number }}" data-status="{{ $now['status'] }}" :data-status="state({{ $table->id }}).status" role="button" tabindex="0"
                                @click="pick({{ $table->id }}, @js($table->number), {{ $table->seats }})" @keydown.enter="pick({{ $table->id }}, @js($table->number), {{ $table->seats }})">
                                @if ($table->shape === \Modules\Restaurant\Enums\TableShape::Round)
                                    <ellipse cx="{{ $w / 2 }}" cy="{{ $h / 2 }}" rx="{{ $w / 2 }}" ry="{{ $h / 2 }}" class="floor-table__top" />
                                @else
                                    <rect width="{{ $w }}" height="{{ $h }}" rx="8" class="floor-table__top" />
                                @endif
                                <text x="{{ $w / 2 }}" y="{{ $h / 2 - 2 }}" text-anchor="middle" class="floor-table__number" :y="state({{ $table->id }}).order_url ? {{ $h / 2 - 10 }} : {{ $h / 2 - 2 }}">{{ $table->number }}</text>
                                <text x="{{ $w / 2 }}" y="{{ $h / 2 + 16 }}" text-anchor="middle" class="floor-table__seats" x-show="! state({{ $table->id }}).order_url">{{ trans_choice(':count seat|:count seats', $table->seats) }}</text>
                                <text x="{{ $w / 2 }}" y="{{ $h / 2 + 8 }}" text-anchor="middle" class="floor-table__seats" x-show="state({{ $table->id }}).order_url" x-text="state({{ $table->id }}).subtotal" x-cloak></text>
                                <text x="{{ $w / 2 }}" y="{{ $h / 2 + 24 }}" text-anchor="middle" class="floor-table__seats" x-show="state({{ $table->id }}).order_url" x-text="state({{ $table->id }}).minutes + ' {{ __('min') }}'" x-cloak data-elapsed></text>
                            </g>
                        @endforeach
                    </svg>
                @endforeach
                <div class="d-flex flex-wrap gap-3 small mt-2">
                    <span><span class="pos-legend pos-legend--available"></span> {{ __('Free') }}</span>
                    <span><span class="pos-legend pos-legend--occupied"></span> {{ __('Occupied') }}</span>
                    <span><span class="pos-legend pos-legend--bill_printed"></span> {{ __('Bill printed') }}</span>
                    <span><span class="pos-legend pos-legend--reserved"></span> {{ __('Reserved') }}</span>
                </div>
            @endif
        </section>

        <aside class="pos-card pos-floor__side">
            <div x-show="table" x-cloak data-open-table>
                <form method="POST" action="{{ route('pos.orders.open') }}" x-data="{ covers: 2 }">
                    @csrf
                    <input type="hidden" name="type" value="dine_in">
                    <input type="hidden" name="table_id" :value="table?.id">
                    <input type="hidden" name="covers" :value="covers">
                    <h2 class="h5">{{ __('Table') }} <span x-text="table?.number"></span></h2>
                    <p class="text-body-secondary mb-2">{{ __('How many guests?') }}</p>
                    <div class="pos-covers mb-3">
                        @foreach (range(1, 8) as $count)
                            <button type="button" class="btn pos-btn" :class="covers === {{ $count }} ? 'btn-primary' : 'btn-outline-primary'" @click="covers = {{ $count }}" data-covers="{{ $count }}">{{ $count }}</button>
                        @endforeach
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success btn-lg pos-btn flex-grow-1" data-start-order><i class="bi bi-plus-lg"></i> {{ __('Start order') }}</button>
                        <button type="button" class="btn btn-outline-secondary pos-btn" @click="table = null">{{ __('Cancel') }}</button>
                    </div>
                </form>
                <hr>
            </div>

            <div class="d-grid gap-2 mb-3">
                <form method="POST" action="{{ route('pos.orders.open') }}">
                    @csrf
                    <input type="hidden" name="type" value="takeaway">
                    <button type="submit" class="btn btn-outline-primary btn-lg pos-btn w-100" data-takeaway><i class="bi bi-bag"></i> {{ __('New takeaway') }}</button>
                </form>
                <button type="button" class="btn btn-outline-success btn-lg pos-btn" :class="{ active: mode === 'room' }" @click="start('room')" data-room-service><i class="bi bi-door-open"></i> {{ __('Room service') }}</button>
                <button type="button" class="btn btn-outline-success btn-lg pos-btn" :class="{ active: mode === 'delivery' }" @click="start('delivery')" data-delivery><i class="bi bi-truck"></i> {{ __('Delivery') }}</button>
                @can('restaurant.order.staff-meal')
                    <button type="button" class="btn btn-outline-warning btn-lg pos-btn" :class="{ active: mode === 'staff' }" @click="start('staff')" data-staff-meal-start><i class="bi bi-person-badge"></i> {{ __('Staff meal') }}</button>
                @endcan
            </div>

            <form method="POST" action="{{ route('pos.orders.open') }}" x-show="mode === 'room'" x-cloak class="mb-3" data-room-service-form>
                @csrf
                <input type="hidden" name="type" value="room_service">
                <input type="hidden" name="reservation_id" :value="stay?.reservationId">
                <input type="search" class="form-control mb-2" x-model="term" @input.debounce.300ms="searchStays(term)" placeholder="{{ __('Room, guest or booking') }}" aria-label="{{ __('Room, guest or booking') }}" autocomplete="off">
                <div class="pos-stays mb-2">
                    <template x-for="s in stays" :key="s.reservationId">
                        <button type="button" class="pos-stay" :class="{ 'pos-stay--chosen': stay && stay.reservationId === s.reservationId }" @click="stay = s" :disabled="s.noRoomCharges && false" :data-stay="s.code">
                            <span class="fw-semibold" x-text="s.rooms.join(', ') + ' · ' + s.guestName"></span><span class="small" x-text="s.code"></span>
                        </button>
                    </template>
                </div>
                <button type="submit" class="btn btn-success btn-lg pos-btn w-100" :disabled="! stay" data-start-room-service>{{ __('Start order') }}</button>
            </form>
            <form method="POST" action="{{ route('pos.orders.open') }}" x-show="mode === 'delivery'" x-cloak class="mb-3" data-delivery-form>
                @csrf
                <input type="hidden" name="type" value="location_delivery">
                <label class="form-label" for="delivery-location">{{ __('Deliver to') }}</label>
                <input type="text" id="delivery-location" name="location" maxlength="190" class="form-control mb-2" placeholder="{{ __('The pool, the beach, a cottage terrace…') }}" required>
                <button type="submit" class="btn btn-success btn-lg pos-btn w-100" data-start-delivery>{{ __('Start order') }}</button>
            </form>
            <form method="POST" action="{{ route('pos.orders.open') }}" x-show="mode === 'staff'" x-cloak class="mb-3" data-staff-meal-form>
                @csrf
                <input type="hidden" name="type" value="staff_meal">
                <label class="form-label" for="staff-name">{{ __('For') }}</label>
                <input type="text" id="staff-name" name="name" maxlength="190" class="form-control mb-2" placeholder="{{ __('Person or team') }}" required>
                <button type="submit" class="btn btn-warning btn-lg pos-btn w-100" data-start-staff-meal>{{ __('Start order') }}</button>
            </form>
            <p class="alert alert-danger py-2" x-show="error" x-text="error" x-cloak></p>

            <div class="mb-3" x-show="floor.reservations.length" x-cloak data-reservations>
                <h2 class="h6">{{ __('Reservations to come') }}</h2>
                <template x-for="r in floor.reservations" :key="r.id">
                    <div class="pos-reservation" :data-reservation="r.name">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold" x-text="r.time + ' · ' + r.name"></span>
                            <span class="small" x-text="r.party + ' {{ __('guests') }}' + (r.table ? ' · ' + r.table : '')"></span>
                        </div>
                        <div class="small text-body-secondary" x-show="r.occasion || r.notes" x-text="[r.occasion, r.notes].filter(Boolean).join(' · ')"></div>
                        <div class="d-flex gap-2 mt-1" x-show="r.table_id">
                            <form method="POST" :action="r.seat_url">@csrf<button type="submit" class="btn btn-sm btn-primary" data-seat>{{ __('Seat') }}</button></form>
                            <form method="POST" :action="r.close_url" data-confirm="{{ __('Mark as no-show?') }}">@csrf<input type="hidden" name="status" value="no_show"><button type="submit" class="btn btn-sm btn-outline-danger">{{ __('No-show') }}</button></form>
                        </div>
                        <p class="small text-warning mb-0" x-show="! r.table_id">{{ __('No table yet: assign one in the back office.') }}</p>
                    </div>
                </template>
            </div>

            <h2 class="h6">{{ __('Open orders') }}</h2>
            @foreach ($floor['orders'] as $open)
                {{-- Rendered by the server first (also without JavaScript), then kept live by Alpine below. --}}
                <a href="{{ $open['url'] }}" class="pos-order-link" data-open-order="{{ $open['order_no'] }}" x-show="false">
                    <span><span class="fw-semibold">{{ $open['where'] }}</span><span class="d-block small text-body-secondary">{{ $open['meta'] }}</span></span>
                    <span class="font-monospace">{{ $open['subtotal'] }}</span>
                </a>
            @endforeach
            <template x-for="open in floor.orders" :key="open.id">
                <a :href="open.url" class="pos-order-link" :data-live-order="open.order_no">
                    <span><span class="fw-semibold" x-text="open.where"></span><span class="d-block small text-body-secondary" x-text="open.meta"></span>
                        <span class="badge" :class="open.delivery ? 'text-bg-' + open.delivery.color : ''" x-show="open.delivery" x-text="open.delivery ? open.delivery.label + (open.delivery.guest ? ' · ' + open.delivery.guest : '') : ''" :data-delivery-status="open.delivery?.status"></span></span>
                    <span class="font-monospace" x-text="open.subtotal"></span>
                </a>
                <button type="button" class="btn btn-sm btn-info mb-2 w-100" x-show="open.delivery && nextDelivery(open)" @click="advance(open, nextDelivery(open))"
                    x-text="open.delivery && nextDelivery(open) === 'delivered' ? '{{ __('Delivered') }}' : '{{ __('Out for delivery') }}'" :data-advance="open.order_no"></button>
            </template>
            <p class="text-body-secondary" x-show="floor.orders.length === 0" @if ($floor['orders'] !== []) x-cloak @endif>{{ __('No open orders.') }}</p>
        </aside>
    </div>
</x-layouts::pos>
