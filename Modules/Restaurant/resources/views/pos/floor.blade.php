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

    <div class="pos-floor" x-data="posFloor(@js(['floor' => $floor, 'channel' => $channel, 'urls' => ['floor' => route('pos.floor.data')]]))" x-init="area = {{ $areas->first()->id ?? 0 }}">
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

            <form method="POST" action="{{ route('pos.orders.open') }}" class="mb-3">
                @csrf
                <input type="hidden" name="type" value="takeaway">
                <button type="submit" class="btn btn-outline-primary btn-lg pos-btn w-100" data-takeaway><i class="bi bi-bag"></i> {{ __('New takeaway') }}</button>
            </form>

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
                    <span><span class="fw-semibold" x-text="open.where"></span><span class="d-block small text-body-secondary" x-text="open.meta"></span></span>
                    <span class="font-monospace" x-text="open.subtotal"></span>
                </a>
            </template>
            <p class="text-body-secondary" x-show="floor.orders.length === 0" @if ($floor['orders'] !== []) x-cloak @endif>{{ __('No open orders.') }}</p>
        </aside>
    </div>
</x-layouts::pos>
