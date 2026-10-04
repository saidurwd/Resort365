@php
    $date = \Carbon\CarbonImmutable::parse($property->businessDate);
@endphp
<x-layouts::app :title="__('Room status')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Room status') => null]">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-board-counts>
        <span class="fw-semibold me-1">{{ $date->format('D d M Y') }}</span>
        <span class="badge text-bg-success">{{ __('Clean') }} {{ $counts['clean'] }}</span>
        <span class="badge text-bg-primary">{{ __('Inspected') }} {{ $counts['inspected'] }}</span>
        <span class="badge text-bg-danger">{{ __('Dirty') }} {{ $counts['dirty'] }}</span>
        <span class="badge text-bg-secondary">{{ __('Occupied') }} {{ $counts['occupied'] }}</span>
        <span class="badge border text-body-secondary">{{ __('Vacant') }} {{ $counts['vacant'] }}</span>
        @if ($counts['blocked'])<span class="badge text-bg-warning">{{ __('Blocked') }} {{ $counts['blocked'] }}</span>@endif
    </div>

    <form method="POST" action="{{ route('housekeeping.board.status') }}" data-board-form>
        @csrf
        @foreach ($cottages as $group)
            <x-card :title="$group['cottage']->name.' · '.$group['cottage']->code" icon="bi-house">
                <div class="room-board">
                    @foreach ($group['rooms'] as $tile)
                        @php
                            $room = $tile['room'];
                            $occupancy = $tile['occupancy'];
                        @endphp
                        <label @class(['room-tile', 'room-tile--'.$tile['status']->color(), 'room-tile--blocked' => $tile['block']]) data-room-tile="{{ $room->number }}" data-status="{{ $tile['status']->value }}">
                            <span class="d-flex justify-content-between align-items-start">
                                <span class="room-tile__number">{{ $room->number }}</span>
                                @if ($canUpdate)
                                    <input type="checkbox" class="form-check-input" name="room_ids[]" value="{{ $room->id }}" aria-label="{{ __('Select room :number', ['number' => $room->number]) }}">
                                @endif
                            </span>
                            <span class="room-tile__status">{{ $tile['status']->label() }}</span>
                            <span class="room-tile__meta">
                                @if ($tile['block'])
                                    <span class="badge text-bg-{{ $tile['block']->type->color() }}" data-block>{{ $tile['block']->type === \Modules\Housekeeping\Enums\BlockType::OutOfOrder ? __('OOO') : __('OOS') }}</span>
                                @endif
                                @if ($tile['occupied'])
                                    <span class="badge text-bg-secondary" data-occupied><i class="bi bi-person-fill"></i> {{ __('Occupied') }}</span>
                                @else
                                    <span class="badge border text-body-secondary">{{ __('Vacant') }}</span>
                                @endif
                                @if ($occupancy?->departing)<span class="badge text-bg-warning" data-departing>{{ __('Due out') }}</span>@endif
                                @if ($occupancy?->arriving)<span class="badge text-bg-info" data-arriving>{{ __('Arriving') }}</span>@endif
                            </span>
                            @if ($occupancy?->guestName)
                                <span class="room-tile__guest small text-truncate">{{ $occupancy->guestName }}</span>
                            @endif
                            @if ($tile['task'])
                                <span class="small" data-task><i class="bi bi-brush"></i> {{ $tile['task']->type->label() }} · {{ $tile['task']->status->label() }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </x-card>
        @endforeach

        @if ($canUpdate)
            <div class="card card-body d-flex flex-row flex-wrap gap-2 align-items-end board-actions">
                <x-form.select name="status" :label="__('Set the selected rooms to')" :options="$statuses" :search="false" wrapper-class="mb-0" required />
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-square"></i> {{ __('Update rooms') }}</button>
            </div>
        @endif
    </form>
</x-layouts::app>
