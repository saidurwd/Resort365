@php
    $weekdays = [0 => __('Sun'), 1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
    $previous = \Carbon\CarbonImmutable::parse($from)->subDays($days)->toDateString();
    $next = \Carbon\CarbonImmutable::parse($from)->addDays($days)->toDateString();
    $query = fn (array $extra) => route('hr.roster.index', array_filter([...['from' => $from, 'days' => $days, 'department' => $department], ...$extra]));
@endphp

<x-layouts::app :title="__('Roster')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Roster') => null]">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
        <div class="btn-group" data-roster-range>
            <a class="btn btn-sm btn-outline-secondary" href="{{ $query(['from' => $previous]) }}"><i class="bi bi-chevron-left"></i></a>
            @foreach ([7 => __('Week'), 14 => __('2 weeks'), 31 => __('Month')] as $option => $label)
                <a @class(['btn', 'btn-sm', 'btn-primary' => $days === $option, 'btn-outline-secondary' => $days !== $option]) href="{{ $query(['days' => $option]) }}">{{ $label }}</a>
            @endforeach
            <a class="btn btn-sm btn-outline-secondary" href="{{ $query(['from' => $next]) }}"><i class="bi bi-chevron-right"></i></a>
        </div>
        <form method="GET" action="{{ route('hr.roster.index') }}" class="d-flex gap-2">
            <input type="hidden" name="from" value="{{ $from }}">
            <input type="hidden" name="days" value="{{ $days }}">
            <select name="department" class="form-select form-select-sm" aria-label="{{ __('Department') }}" data-submit-on-change>
                <option value="">{{ __('All departments') }}</option>
                @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($department === $id)>{{ $name }}</option>@endforeach
            </select>
        </form>
    </div>

    <form method="POST" action="{{ route('hr.roster.update', ['action' => 'assign']) }}" x-data data-roster-form>
        @csrf
        @if ($canManage)
            <x-card :title="__('Change the roster for the ticked employees')" icon="bi-calendar-week">
                <div class="row align-items-end">
                    <div class="col-md-2"><x-form.date name="from" :label="__('From')" :value="$from" wrapper-class="mb-2" /></div>
                    <div class="col-md-2"><x-form.date name="to" :label="__('To')" :value="\Carbon\CarbonImmutable::parse($from)->addDays($days - 1)->toDateString()" wrapper-class="mb-2" /></div>
                    <div class="col-md-3">
                        <x-form.select name="shift_id" :label="__('Shift')" :options="$shifts->mapWithKeys(fn ($shift) => [$shift->id => $shift->code.' · '.$shift->name.' ('.$shift->window().')'])->all()" :placeholder="__('Day off')" wrapper-class="mb-2" />
                    </div>
                    <div class="col-md-5">
                        <div class="small text-body-secondary mb-1">{{ __('Only on these days (none ticked = every day)') }}</div>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach ($weekdays as $number => $label)
                                <div class="form-check form-check-inline me-0"><input class="form-check-input" type="checkbox" name="weekdays[]" value="{{ $number }}" id="wd-{{ $number }}"><label class="form-check-label" for="wd-{{ $number }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm" data-assign><i class="bi bi-check2-square"></i> {{ __('Assign') }}</button>
                    <button type="submit" class="btn btn-outline-danger btn-sm" formaction="{{ route('hr.roster.update', ['action' => 'clear']) }}" data-clear><i class="bi bi-eraser"></i> {{ __('Clear these dates') }}</button>
                    <span class="mx-2 text-body-secondary">|</span>
                    <div><label class="form-label small mb-0" for="week_start">{{ __('Copy the week starting') }}</label><input type="date" class="form-control form-control-sm" name="week_start" id="week_start" value="{{ $from }}"></div>
                    <div><label class="form-label small mb-0" for="weeks">{{ __('for the next (weeks)') }}</label><input type="number" min="1" max="12" class="form-control form-control-sm" name="weeks" id="weeks" value="4"></div>
                    <button type="submit" class="btn btn-outline-secondary btn-sm" formaction="{{ route('hr.roster.update', ['action' => 'copy']) }}" data-copy><i class="bi bi-files"></i> {{ __('Copy week') }}</button>
                </div>
            </x-card>
        @endif

        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle text-center mb-0" data-roster-grid>
                    <thead>
                        <tr>
                            @if ($canManage)<th class="ps-2"><input type="checkbox" class="form-check-input" aria-label="{{ __('Tick all') }}" @change="document.querySelectorAll('[data-roster-employee]').forEach(box => box.checked = $event.target.checked)"></th>@endif
                            <th class="text-start">{{ __('Employee') }}</th>
                            @foreach ($grid['dates'] as $date)
                                @php($day = \Carbon\CarbonImmutable::parse($date))
                                <th @class(['small', 'table-primary' => isset($grid['holidays'][$date]), 'table-light' => ! isset($grid['holidays'][$date]) && $date === $today]) title="{{ $grid['holidays'][$date]->name ?? '' }}">{{ $day->format('D') }}<br>{{ $day->format('d') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($grid['employees'] as $row)
                            <tr data-roster-row="{{ $row['employee']->employee_no }}">
                                @if ($canManage)<td class="ps-2"><input type="checkbox" class="form-check-input" name="employee_ids[]" value="{{ $row['employee']->id }}" data-roster-employee aria-label="{{ $row['employee']->full_name }}"></td>@endif
                                <td class="text-start text-nowrap"><a href="{{ route('hr.employees.show', $row['employee']) }}">{{ $row['employee']->full_name }}</a><div class="small text-body-secondary">{{ $row['employee']->designation->name }}</div></td>
                                @foreach ($row['cells'] as $date => $cell)
                                    <td @class(['p-1', 'table-primary' => isset($grid['holidays'][$date])]) data-cell="{{ $date }}">
                                        @if ($cell['leave'])<span class="badge text-bg-{{ $cell['leave']->color }}" title="{{ $cell['leave']->name }}" data-leave-cell>{{ $cell['leave']->code }}</span>
                                        @elseif ($cell['shift'])<span class="badge text-bg-{{ $cell['shift']->color }}" title="{{ $cell['shift']->name }} {{ $cell['shift']->window() }}">{{ $cell['shift']->code }}</span>
                                        @elseif ($cell['planned'])<span class="small text-body-secondary">{{ __('OFF') }}</span>
                                        @else<span class="text-body-tertiary">·</span>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($grid['dates']) + 2 }}"><x-empty-state :title="__('No employees')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </form>
</x-layouts::app>
