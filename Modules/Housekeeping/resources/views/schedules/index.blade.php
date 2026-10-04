<x-layouts::app :title="__('Preventive maintenance')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Housekeeping') => null, __('Preventive maintenance') => null]">
    <x-slot:actions>
        <form method="POST" action="{{ route('housekeeping.schedules.run') }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary" data-run-schedules><i class="bi bi-play"></i> {{ __('Open due work orders now') }}</button>
        </form>
        <a href="{{ route('housekeeping.schedules.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New schedule') }}</a>
    </x-slot:actions>
    <x-card body-class="p-0">
        @if ($schedules->isEmpty())
            <x-empty-state icon="bi-calendar2-check" :title="__('No schedules yet')" :message="__('E.g. AC servicing every 90 days.')" />
        @else
            <table class="table align-middle mb-0" data-schedules>
                <thead><tr><th class="ps-3">{{ __('Task') }}</th><th>{{ __('Where') }}</th><th>{{ __('Every') }}</th><th>{{ __('Next due') }}</th><th>{{ __('Technician') }}</th><th class="pe-3"></th></tr></thead>
                <tbody>
                    @foreach ($schedules as $schedule)
                        <tr @class(['text-body-secondary' => ! $schedule->is_active]) data-schedule="{{ $schedule->id }}">
                            <td class="ps-3 fw-semibold">{{ $schedule->title }} <span class="small text-body-secondary">· {{ $schedule->category->label() }}</span></td>
                            <td>{{ $schedule->room_id ? ($rooms[$schedule->room_id] ?? '') : $schedule->location }}</td>
                            <td>{{ trans_choice(':count day|:count days', $schedule->interval_days) }}</td>
                            <td data-next-due>{{ $schedule->is_active ? $schedule->next_due_on->format('d M Y') : __('Paused') }}</td>
                            <td>{{ $schedule->assigned_to ? ($names[$schedule->assigned_to] ?? '') : '—' }}</td>
                            <td class="pe-3 text-end"><a href="{{ route('housekeeping.schedules.edit', $schedule) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts::app>
