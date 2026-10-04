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
</x-layouts::app>
