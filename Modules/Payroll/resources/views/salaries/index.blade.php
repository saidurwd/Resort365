@php($money = fn (string $value): string => number_format((float) $value, 2))

<x-layouts::app :title="__('Salaries')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Salaries') => null]">
    <form method="GET" action="{{ route('payroll.salaries.index') }}" class="d-flex gap-2 mb-3">
        <select name="department" class="form-select form-select-sm w-auto" aria-label="{{ __('Department') }}" onchange="this.form.submit()">
            <option value="">{{ __('All departments') }}</option>
            @foreach ($departments as $id => $name)<option value="{{ $id }}" @selected($department === $id)>{{ $name }}</option>@endforeach
        </select>
    </form>
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-salaries>
                <thead><tr><th class="ps-3">{{ __('Employee') }}</th><th>{{ __('Structure') }}</th><th class="text-end">{{ __('Basic') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Deductions') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Net') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr data-salary-row="{{ $row['employee']->employeeNo }}">
                            <td class="ps-3"><a href="{{ route('payroll.salaries.show', $row['employee']->id) }}">{{ $row['employee']->name }}</a> <span class="small text-body-secondary">{{ $row['employee']->employeeNo }} · {{ $row['employee']->designation }}</span></td>
                            @if ($row['salary'])
                                <td>{{ $row['salary']->structure->name }}</td>
                                <td class="text-end font-monospace">{{ $money((string) $row['salary']->basic) }}</td>
                                <td class="text-end font-monospace">{{ $row['breakdown'] ? $money($row['breakdown']->gross) : '—' }}</td>
                                <td class="text-end font-monospace">{{ $row['breakdown'] ? $money($row['breakdown']->deductions) : '—' }}</td>
                                <td class="text-end font-monospace">{{ $row['breakdown'] ? $money($row['breakdown']->tax) : '—' }}</td>
                                <td class="text-end font-monospace pe-3 fw-semibold">{{ $row['breakdown'] ? $money($row['breakdown']->net) : '—' }}</td>
                            @else
                                <td colspan="6" class="text-body-secondary">{{ __('No salary set') }}@if ($canManage) · <a href="{{ route('payroll.salaries.show', $row['employee']->id) }}">{{ __('Set it') }}</a>@endif</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state :title="__('No employees')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <p class="small text-body-secondary mt-2">{{ __('Monthly figures on :date, from each employee\'s salary and structure.', ['date' => \Carbon\CarbonImmutable::parse($today)->format('d M Y')]) }}</p>
</x-layouts::app>
