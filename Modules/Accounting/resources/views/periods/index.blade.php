<x-layouts::app :title="__('Fiscal periods')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Fiscal periods') => null]">
    @if ($canManage)
        <x-card :title="__('Open a fiscal year')" icon="bi-plus-lg">
            <form method="POST" action="{{ route('accounting.periods.store') }}" class="d-flex flex-wrap gap-2 align-items-end" data-fiscal-year-form>
                @csrf
                <x-form.input name="starts_on" type="date" :label="__('Starts on')" :value="$nextStart" wrapper-class="mb-0" required />
                <x-form.input name="name" :label="__('Name')" :help="__('Optional')" wrapper-class="mb-0" />
                <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> {{ __('Open year') }}</button>
            </form>
        </x-card>
    @endif

    @forelse ($years as $year)
        <x-card :title="$year->name.' · '.$year->starts_on->format('d M Y').' – '.$year->ends_on->format('d M Y')" icon="bi-calendar3" body-class="p-0" data-year="{{ $year->name }}">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th class="ps-3">{{ __('Period') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($year->periods as $period)
                            <tr data-period="{{ $period->name }}">
                                <td class="ps-3">{{ $period->name }}</td>
                                <td>{{ $period->starts_on->format('d M Y') }}</td>
                                <td>{{ $period->ends_on->format('d M Y') }}</td>
                                <td><x-status-badge :status="$period->status" /></td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if ($canManage)
                                        @if ($period->status === \Modules\Accounting\Enums\PeriodStatus::Open)
                                            <form method="POST" action="{{ route('accounting.periods.update', $period) }}" class="d-inline" data-confirm="{{ __('Close :period? Nothing can be posted into it until it is reopened.', ['period' => $period->name]) }}">@csrf @method('PUT')<input type="hidden" name="status" value="closed"><button class="btn btn-sm btn-outline-warning">{{ __('Close') }}</button></form>
                                        @elseif ($period->status === \Modules\Accounting\Enums\PeriodStatus::Closed)
                                            <form method="POST" action="{{ route('accounting.periods.update', $period) }}" class="d-inline" data-confirm="{{ __('Lock :period?', ['period' => $period->name]) }}">@csrf @method('PUT')<input type="hidden" name="status" value="locked"><button class="btn btn-sm btn-outline-danger">{{ __('Lock') }}</button></form>
                                        @endif
                                        @if ($canReopen && $period->status !== \Modules\Accounting\Enums\PeriodStatus::Open)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reopen-modal" data-modal-action="{{ route('accounting.periods.update', $period) }}" data-reopen="{{ $period->name }}">{{ __('Reopen') }}</button>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
        <x-empty-state icon="bi-calendar3" :title="__('No fiscal year yet')" :message="__('Open one to start posting journal entries.')" />
    @endforelse

    @if ($canReopen)
        <x-modal id="reopen-modal" :title="__('Reopen period')">
            <form method="POST" action="" id="reopen-form" data-reopen-form>
                @csrf @method('PUT')
                <input type="hidden" name="status" value="open">
                <x-form.input name="reason" :label="__('Why is it reopened?')" required />
                <button type="submit" class="btn btn-warning">{{ __('Reopen') }}</button>
            </form>
        </x-modal>
    @endif
</x-layouts::app>
