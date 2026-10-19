<x-layouts::app :title="__('Leave balances')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Leave balances') => null]">
    <form method="GET" action="{{ route('hr.leave.balances') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-balance-filter>
        <x-form.input name="year" type="number" min="2000" max="2100" :label="__('Year')" :value="$year" wrapper-class="mb-0" />
        <x-form.select name="department" :label="__('Department')" :options="$departments" :value="$department" :placeholder="__('All departments')" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-primary">{{ __('Show') }}</button>
    </form>

    <div x-data="{ employee: '', name: '' }">
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-balances>
                    <thead><tr><th class="ps-3">{{ __('Employee') }}</th>@foreach ($types as $type)<th class="text-end"><span class="badge text-bg-{{ $type->color }}">{{ $type->code }}</span></th>@endforeach @if ($canAdjust)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            @php($mine = ($balances[$employee->id] ?? collect())->keyBy('leave_type_id'))
                            <tr data-balance-row="{{ $employee->employee_no }}">
                                <td class="ps-3"><a href="{{ route('hr.employees.show', $employee) }}">{{ $employee->full_name }}</a> <span class="small text-body-secondary">{{ $employee->designation->name }}</span></td>
                                @foreach ($types as $type)
                                    @php($balance = $mine[$type->id] ?? null)
                                    <td class="text-end font-monospace" data-type="{{ $type->code }}">@if ($balance)<span title="{{ __('Entitled :e + carried :c + adjusted :a − used :u − waiting :p', ['e' => $balance->entitled, 'c' => $balance->carried_forward, 'a' => $balance->adjusted, 'u' => $balance->used, 'p' => $balance->pending]) }}">{{ $balance->available() }}</span>@else<span class="text-body-tertiary">—</span>@endif</td>
                                @endforeach
                                @if ($canAdjust)<td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjust-balance" data-adjust="{{ $employee->employee_no }}" @click="employee = {{ $employee->id }}; name = @js($employee->full_name)"><i class="bi bi-plus-slash-minus"></i> {{ __('Adjust') }}</button></td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $types->count() + 2 }}"><x-empty-state :title="__('No employees')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
        <p class="small text-body-secondary">{{ __('Days available = entitled + carried forward + adjusted − used − waiting for approval. Point at a figure to see the parts.') }}</p>

        @if ($canAdjust)
            <x-modal id="adjust-balance" :title="__('Adjust a balance')">
                <form method="POST" action="{{ route('hr.leave.balances.adjust') }}" id="form-adjust-balance">
                    @csrf
                    <input type="hidden" name="employee_id" x-model="employee">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <p class="fw-semibold" x-text="name"></p>
                    <x-form.select name="leave_type_id" :label="__('Leave type')" :options="$types->pluck('name', 'id')->all()" :placeholder="__('Choose…')" required />
                    <x-form.input name="days" type="number" step="0.5" :label="__('Days to add (minus to take away)')" required />
                    <x-form.input name="reason" :label="__('Reason')" required />
                </form>
                <x-slot:footer><button type="submit" form="form-adjust-balance" class="btn btn-primary">{{ __('Save') }}</button></x-slot:footer>
            </x-modal>
        @endif
    </div>
</x-layouts::app>
