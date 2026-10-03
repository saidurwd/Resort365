<x-layouts::app :title="__('New booking')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('New booking') => null]">
    @include('reservation::bookings.partials.steps')

    @if (! $result)
        <x-card><x-empty-state icon="bi-search" :title="__('Start with the dates')" /></x-card>
    @else
        <form method="POST" action="{{ route('reservation.bookings.choose.store') }}" data-wizard-choose>
            @csrf
            @error('cottages')<div class="alert alert-danger">{{ $message }}</div>@enderror
            @foreach ($result->planViolations as $violation)
                <div class="alert alert-warning">{{ $violation->message() }}</div>
            @endforeach
            <div class="row">
                <div class="col-xl-6">
                    <x-card :title="__('Whole cottages')" icon="bi-houses" body-class="p-0">
                        @forelse ($result->cottages as $option)
                            <label @class(['d-flex gap-3 px-3 py-2 border-bottom', 'opacity-50' => ! $option->isBookable()]) data-choose-cottage="{{ $option->cottage->code }}">
                                <input type="checkbox" class="form-check-input mt-1" name="cottages[]" value="{{ $option->cottage->id }}"
                                    @checked(in_array($option->cottage->id, old('cottages', $chosenCottages))) @disabled(! $option->isBookable())>
                                <span class="flex-grow-1">
                                    <span class="fw-semibold">{{ $option->cottage->name }}</span>
                                    <span class="small text-body-secondary">{{ $option->typeName }} · {{ trans_choice(':count room|:count rooms', count($option->roomNumbers)) }} · {{ __('up to :count guests', ['count' => $option->cottage->maxOccupancy]) }}</span>
                                    <span class="d-block">@include('reservation::availability.partials.violations', ['violations' => $option->violations])</span>
                                </span>
                                <span class="text-end text-nowrap">@include('reservation::availability.partials.price', ['quote' => $option->quote])</span>
                            </label>
                        @empty
                            <x-empty-state icon="bi-houses" :title="__('No whole cottage is free for these dates')" class="py-4" />
                        @endforelse
                    </x-card>
                </div>
                <div class="col-xl-6">
                    <x-card :title="__('Rooms')" icon="bi-door-open" body-class="p-0">
                        @forelse ($result->roomTypes as $option)
                            <div @class(['px-3 py-2 border-bottom', 'opacity-50' => ! $option->isBookable()]) data-choose-room-type="{{ $option->roomType->code }}">
                                <div class="d-flex gap-3">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ $option->roomType->name }} <span class="small text-body-secondary">{{ __('up to :count guests', ['count' => $option->roomType->maxOccupancy]) }}</span></div>
                                        @include('reservation::availability.partials.violations', ['violations' => $option->violations])
                                    </div>
                                    <div class="text-end text-nowrap">@include('reservation::availability.partials.price', ['quote' => $option->quote])<div class="small text-body-secondary">{{ __('per room') }}</div></div>
                                </div>
                                <div class="d-flex flex-wrap gap-3 mt-1">
                                    @foreach ($option->rooms as $room)
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="rooms[]" value="{{ $room->id }}" id="room-{{ $room->id }}"
                                                @checked(in_array($room->id, old('rooms', $chosenRooms))) @disabled(! $option->isBookable())>
                                            <label class="form-check-label" for="room-{{ $room->id }}">{{ __('Room :number', ['number' => $room->number]) }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <x-empty-state icon="bi-door-open" :title="__('No room is free for these dates')" class="py-4" />
                        @endforelse
                    </x-card>
                </div>
            </div>
            <div class="d-flex justify-content-between mb-4">
                <a href="{{ route('reservation.bookings.create') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
                <button type="submit" class="btn btn-primary">{{ __('Continue') }} <i class="bi bi-arrow-right"></i></button>
            </div>
        </form>
    @endif
</x-layouts::app>
