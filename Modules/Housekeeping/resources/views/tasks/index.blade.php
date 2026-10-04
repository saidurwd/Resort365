<x-layouts::app :title="__('Cleaning tasks')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Tasks') => null]">
    <x-slot:actions>
        <form method="POST" action="{{ route('housekeeping.tasks.generate') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary" data-generate><i class="bi bi-arrow-repeat"></i> {{ __('Create today\'s stayover tasks') }}</button>
        </form>
    </x-slot:actions>

    <form method="POST" action="{{ route('housekeeping.tasks.assign') }}" id="assign-form">@csrf</form>

    <x-card :title="__('Tasks for :date', ['date' => $date->format('D d M Y')])" icon="bi-brush" body-class="p-0">
        @if ($tasks->isEmpty())
            <x-empty-state icon="bi-brush" :title="__('No tasks today yet')" :message="__('Check-outs create departure cleans; the night audit (or the button above) creates stayovers.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-tasks>
                    <thead><tr><th class="ps-3"></th><th>{{ __('Room') }}</th><th>{{ __('Task') }}</th><th>{{ __('Status') }}</th><th>{{ __('Attendant') }}</th><th>{{ __('Times') }}</th><th class="pe-3"></th></tr></thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr data-task="{{ $task->id }}">
                                <td class="ps-3">
                                    @if (in_array($task->status, [\Modules\Housekeeping\Enums\TaskStatus::Pending, \Modules\Housekeeping\Enums\TaskStatus::InProgress], true))
                                        <input type="checkbox" class="form-check-input" name="task_ids[]" value="{{ $task->id }}" form="assign-form" aria-label="{{ __('Select task') }}">
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $rooms[$task->room_id] ?? '#'.$task->room_id }}</td>
                                <td><x-status-badge :status="$task->type" /></td>
                                <td><x-status-badge :status="$task->status" /></td>
                                <td>{{ $task->assigned_to ? ($names[$task->assigned_to] ?? '') : '—' }}</td>
                                <td class="small text-body-secondary">
                                    {{ $task->started_at?->format('H:i') }}@if ($task->finished_at)–{{ $task->finished_at->format('H:i') }}@endif
                                    @if ($task->inspected_at) · {{ __('inspected :time', ['time' => $task->inspected_at->format('H:i')]) }}@endif
                                    @if ($task->notes)<div>{{ $task->notes }}</div>@endif
                                </td>
                                <td class="pe-3">@include('housekeeping::tasks.partials.steps')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-end p-3 border-top">
                <x-form.select name="attendant_id" :label="__('Give the selected tasks to')" :options="$attendants" :placeholder="__('Nobody (unassign)')" wrapper-class="mb-0" form="assign-form" />
                <button type="submit" class="btn btn-primary" form="assign-form"><i class="bi bi-person-check"></i> {{ __('Assign') }}</button>
            </div>
        @endif
    </x-card>

    @if ($open->isNotEmpty())
        <x-card :title="__('Still open from earlier days')" icon="bi-exclamation-triangle" body-class="p-0">
            <table class="table table-sm align-middle mb-0">
                @foreach ($open as $task)
                    <tr><td class="ps-3">{{ $task->business_date->format('d M') }}</td><td class="fw-semibold">{{ $rooms[$task->room_id] ?? '' }}</td><td>{{ $task->type->label() }}</td>
                        <td><x-status-badge :status="$task->status" /></td><td class="pe-3">@include('housekeeping::tasks.partials.steps')</td></tr>
                @endforeach
            </table>
        </x-card>
    @endif
</x-layouts::app>
