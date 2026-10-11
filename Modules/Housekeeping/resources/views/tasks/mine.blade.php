<x-layouts::app :title="__('My tasks')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('My tasks') => null]">
    @forelse ($tasks as $task)
        <div class="card mb-2" data-my-task="{{ $task->id }}">
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <span class="fs-4 fw-semibold me-2">{{ $rooms[$task->room_id] ?? '' }}</span>
                <x-status-badge :status="$task->type" />
                <x-status-badge :status="$task->status" />
                @if ($task->assigned_to === null)<span class="badge border text-body-secondary">{{ __('Not assigned') }}</span>@endif
                @if ($task->business_date->toDateString() < $property->businessDate)<span class="small text-danger">{{ __('from :date', ['date' => $task->business_date->format('d M')]) }}</span>@endif
                <div class="ms-auto">@include('housekeeping::tasks.partials.steps')</div>
            </div>
        </div>
    @empty
        <x-card><x-empty-state icon="bi-emoji-smile" :title="__('Nothing to clean right now')" /></x-card>
    @endforelse
    @if (count($amenities) > 0)
        <x-card :title="__('Issue amenities')" icon="bi-box-seam" data-amenities>
            <form method="POST" action="{{ route('housekeeping.amenities.issue') }}">
                @csrf
                <div class="row g-2 mb-2">
                    <div class="col-md-4"><x-form.select name="room_id" :label="__('Room')" :options="$rooms" :value="old('room_id')" :placeholder="__('Not for one room')" /></div>
                </div>
                <div class="row g-2">
                    @foreach ($amenities as $item)
                        <div class="col-6 col-md-3"><label class="form-label small mb-0" for="amenity-{{ $item->id }}">{{ $item->name }} <span class="text-body-secondary">({{ $item->baseUnit }})</span></label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" id="amenity-{{ $item->id }}" name="items[{{ $item->id }}]" data-amenity="{{ $item->sku }}"></div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary mt-3" data-issue-amenities><i class="bi bi-box-arrow-right"></i> {{ __('Issue') }}</button>
            </form>
        </x-card>
    @endif
</x-layouts::app>
