@php
    $today = $businessDate->toDateString();
    $nav = fn (\Carbon\CarbonImmutable $day, ?int $days = null): string => route('reservation.tape-chart', ['from' => $day->toDateString(), 'days' => $days ?? $windowDays]);
@endphp
<x-layouts::app :title="__('Tape chart')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => route('reservation.bookings.index'), __('Tape chart') => null]">
    <x-slot:actions>
        @if ($canBook)
            <a href="{{ route('reservation.bookings.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New booking') }}</a>
        @endif
    </x-slot:actions>

    <x-card body-class="p-0">
        <div class="d-flex flex-wrap gap-2 align-items-center p-2 border-bottom" data-tape-chart-nav>
            <div class="btn-group">
                <a href="{{ $nav($from->subDays($windowDays)) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Earlier') }}"><i class="bi bi-chevron-double-left"></i></a>
                <a href="{{ $nav($from->subDays(7)) }}" class="btn btn-outline-secondary btn-sm">{{ __('−7 days') }}</a>
                <a href="{{ $nav($businessDate->subDay()) }}" class="btn btn-outline-secondary btn-sm">{{ __('Today') }}</a>
                <a href="{{ $nav($from->addDays(7)) }}" class="btn btn-outline-secondary btn-sm">{{ __('+7 days') }}</a>
                <a href="{{ $nav($from->addDays($windowDays)) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('Later') }}"><i class="bi bi-chevron-double-right"></i></a>
            </div>
            <div class="btn-group">
                @foreach ($windows as $window)
                    <a href="{{ $nav($from, $window) }}" @class(['btn btn-sm', 'btn-secondary' => $window === $windowDays, 'btn-outline-secondary' => $window !== $windowDays])>{{ trans_choice(':count day|:count days', $window) }}</a>
                @endforeach
            </div>
            <span class="fw-semibold ms-1">{{ $from->format('d M') }} – {{ $from->addDays($windowDays - 1)->format('d M Y') }}</span>
            <div class="ms-auto d-flex flex-wrap gap-2 small">
                @foreach ([\Modules\Reservation\Enums\ReservationStatus::Tentative, \Modules\Reservation\Enums\ReservationStatus::Confirmed, \Modules\Reservation\Enums\ReservationStatus::CheckedIn, \Modules\Reservation\Enums\ReservationStatus::CheckedOut] as $status)
                    <x-status-badge :status="$status" />
                @endforeach
                <span class="badge tape-chart__legend-block">{{ __('Blocked') }}</span>
            </div>
        </div>

        <div class="tape-chart-scroll" x-data="tapeChart(@js(['moveUrl' => route('reservation.tape-chart.move'), 'canMove' => $canMove]))">
            <div class="alert m-2" x-show="message" x-cloak :class="ok ? 'alert-success' : 'alert-danger'" role="alert" data-tape-chart-message>
                <span x-text="message"></span>
            </div>

            <div class="tape-chart tape-chart--{{ $windowDays }}" data-tape-chart data-from="{{ $from->toDateString() }}">
                <div class="tape-chart__row tape-chart__row--head">
                    <div class="tape-chart__room">{{ __('Room') }}</div>
                    <div class="tape-chart__lane">
                        @foreach ($days as $i => $day)
                            <div @class(['tape-chart__day', 'tc-col-'.$i, 'is-today' => $day->toDateString() === $today, 'is-past' => $day->toDateString() < $today]) data-day="{{ $day->toDateString() }}">
                                <span class="small text-body-secondary">{{ $day->translatedFormat('D') }}</span>
                                <span class="fw-semibold">{{ $day->format('d') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                @forelse ($cottages as $group)
                    <div class="tape-chart__group">{{ $group['cottage']->name }} <span class="text-body-secondary small">{{ $group['cottage']->code }}</span></div>
                    @foreach ($group['rooms'] as $room)
                        <div class="tape-chart__row" data-room-id="{{ $room->id }}" data-room="{{ $room->number }}">
                            <div class="tape-chart__room">
                                <span class="fw-semibold">{{ $room->number }}</span>
                                <span class="small text-body-secondary">{{ $roomTypes[$room->roomTypeId] ?? '' }}</span>
                            </div>
                            <div class="tape-chart__lane" :class="{ 'is-drop-target': target === {{ $room->id }} }"
                                @dragover.prevent="over({{ $room->id }})" @dragleave="leave({{ $room->id }})" @drop.prevent="drop({{ $room->id }})">
                                @foreach ($days as $i => $day)
                                    @if ($canBook && $day->toDateString() >= $today)
                                        <a href="{{ route('reservation.bookings.create', ['check_in' => $day->toDateString(), 'check_out' => $day->addDay()->toDateString()]) }}"
                                            @class(['tape-chart__cell', 'tc-col-'.$i, 'is-today' => $day->toDateString() === $today]) title="{{ __('Book from :date', ['date' => $day->format('d M')]) }}"
                                            data-cell="{{ $day->toDateString() }}"><span class="visually-hidden">{{ __('Book room :number from :date', ['number' => $room->number, 'date' => $day->format('d M')]) }}</span></a>
                                    @else
                                        <div @class(['tape-chart__cell', 'tc-col-'.$i, 'is-today' => $day->toDateString() === $today, 'is-past' => $day->toDateString() < $today])></div>
                                    @endif
                                @endforeach
                                @foreach ($bars[$room->id] ?? [] as $bar)
                                    @php($movable = $canMove && $bar['draggable'])
                                    <a @if ($bar['url']) href="{{ $bar['url'] }}" @endif
                                        @class([
                                            'tape-chart__bar', 'tc-col-'.$bar['start'], 'tc-span-'.$bar['span'],
                                            'text-bg-'.$bar['status']?->color() => $bar['status'] !== null,
                                            'tape-chart__bar--block' => $bar['status'] === null,
                                            'continues-before' => $bar['continues_before'], 'continues-after' => $bar['continues_after'],
                                            'is-movable' => $movable,
                                        ])
                                        title="{{ trim(($bar['code'] ? $bar['code'].' · ' : '').$bar['label'].($bar['status'] ? ' · '.$bar['status']->label() : '')) }}"
                                        data-bar data-item-id="{{ $bar['item_id'] }}" data-code="{{ $bar['code'] }}" data-start="{{ $bar['start'] }}" data-span="{{ $bar['span'] }}"
                                        @if ($movable) draggable="true" @dragstart="start($event, {{ $bar['item_id'] }})" @dragend="end()" @endif>
                                        <span class="tape-chart__label">{{ $bar['label'] ?: $bar['code'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @empty
                    <x-empty-state icon="bi-grid-3x3" :title="__('No rooms yet')" :message="__('Add cottages and rooms under Setup first.')" />
                @endforelse
            </div>
        </div>

        @if ($canMove)
            <p class="small text-body-secondary px-3 py-2 mb-0 border-top">
                <i class="bi bi-arrows-move"></i>
                {{ __('Drag a room booking to another room: in house, the remaining nights move at the same rate; before arrival, it moves to a room of the same type at the same price. Whole cottages are moved with Change stay.') }}
            </p>
        @endif
    </x-card>
</x-layouts::app>
