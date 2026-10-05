@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $canvas = ['width' => \Modules\Restaurant\Services\FloorPlanGeometry::WIDTH, 'height' => \Modules\Restaurant\Services\FloorPlanGeometry::HEIGHT];
@endphp
<x-layouts::pos :title="__('Floor')" :terminal="$terminal">
    <x-slot:status>
        @if ($businessDate)<span class="pos-bar__meta"><i class="bi bi-calendar3"></i> {{ \Illuminate\Support\Carbon::parse($businessDate)->format('D d M') }}</span>@endif
        <a href="{{ route('pos.main') }}" class="btn pos-btn btn-outline-secondary"><i class="bi bi-cash-coin"></i> {{ __('Session') }}</a>
    </x-slot:status>

    <div x-data="posIdle({{ $autoLockMinutes }})"></div>

    <div class="pos-floor" x-data="{ area: {{ $areas->first()->id ?? 0 }}, table: null }">
        <section class="pos-card pos-floor__plan">
            @if ($areas->isEmpty())
                <x-empty-state icon="bi-grid-3x3" :title="__('No tables in this outlet')" :message="__('Use takeaway, or add dining areas and tables in the outlet setup.')" />
            @else
                <div class="d-flex flex-wrap gap-2 mb-3" role="tablist">
                    @foreach ($areas as $area)
                        <button type="button" class="btn pos-btn" :class="area === {{ $area->id }} ? 'btn-primary' : 'btn-outline-primary'" @click="area = {{ $area->id }}" data-area="{{ $area->id }}">{{ $area->name }}</button>
                    @endforeach
                </div>
                @foreach ($areas as $area)
                    <svg viewBox="0 0 {{ $canvas['width'] }} {{ $canvas['height'] }}" class="floor-plan pos-floor__svg" role="img" aria-label="{{ __('Tables in :area', ['area' => $area->name]) }}" x-show="area === {{ $area->id }}" @if (! $loop->first) x-cloak @endif>
                        <rect width="{{ $canvas['width'] }}" height="{{ $canvas['height'] }}" class="floor-plan__floor" />
                        @foreach ($area->tables as $table)
                            @php
                                [$w, $h] = $table->shape->size($table->seats);
                                $order = $byTable->get($table->id);
                            @endphp
                            <g transform="translate({{ $table->pos_x }} {{ $table->pos_y }})" @class(['floor-table', 'pos-table', 'pos-table--'.($order ? 'occupied' : $table->status->value)])
                                data-pos-table="{{ $table->number }}" data-status="{{ $order ? 'occupied' : $table->status->value }}" role="button" tabindex="0"
                                @if ($order) @click="window.location = @js(route('pos.orders.show', $order))" @keydown.enter="window.location = @js(route('pos.orders.show', $order))"
                                @else @click="table = { id: {{ $table->id }}, number: @js($table->number), seats: {{ $table->seats }} }" @keydown.enter="table = { id: {{ $table->id }}, number: @js($table->number), seats: {{ $table->seats }} }" @endif>
                                @if ($table->shape === \Modules\Restaurant\Enums\TableShape::Round)
                                    <ellipse cx="{{ $w / 2 }}" cy="{{ $h / 2 }}" rx="{{ $w / 2 }}" ry="{{ $h / 2 }}" class="floor-table__top" />
                                @else
                                    <rect width="{{ $w }}" height="{{ $h }}" rx="8" class="floor-table__top" />
                                @endif
                                <text x="{{ $w / 2 }}" y="{{ $h / 2 - ($order ? 10 : 2) }}" text-anchor="middle" class="floor-table__number">{{ $table->number }}</text>
                                @if ($order)
                                    <text x="{{ $w / 2 }}" y="{{ $h / 2 + 8 }}" text-anchor="middle" class="floor-table__seats">{{ $money($order->subtotal) }}</text>
                                    <text x="{{ $w / 2 }}" y="{{ $h / 2 + 24 }}" text-anchor="middle" class="floor-table__seats" data-elapsed>{{ __(':minutes min', ['minutes' => (int) $order->opened_at->diffInMinutes(now())]) }}</text>
                                @else
                                    <text x="{{ $w / 2 }}" y="{{ $h / 2 + 16 }}" text-anchor="middle" class="floor-table__seats">{{ trans_choice(':count seat|:count seats', $table->seats) }}</text>
                                @endif
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
            @forelse ($byTable->values()->concat($takeaway)->sortBy('opened_at') as $open)
                <a href="{{ route('pos.orders.show', $open) }}" class="pos-order-link" data-open-order="{{ $open->order_no }}">
                    <span>
                        <span class="fw-semibold">{{ $open->dining_table_id ? __('Table :number', ['number' => $open->table?->number]) : __('Takeaway') }}</span>
                        <span class="d-block small text-body-secondary">{{ $open->order_no }} · {{ $names[$open->waiter_id] ?? '' }} · {{ __(':minutes min', ['minutes' => (int) $open->opened_at->diffInMinutes(now())]) }}</span>
                    </span>
                    <span class="font-monospace">{{ $money($open->subtotal) }}</span>
                </a>
            @empty
                <p class="text-body-secondary">{{ __('No open orders.') }}</p>
            @endforelse
        </aside>
    </div>
</x-layouts::pos>
