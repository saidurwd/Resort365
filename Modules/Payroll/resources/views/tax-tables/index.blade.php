<x-layouts::app :title="__('Income tax tables')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Payroll') => null, __('Income tax tables') => null]">
    @if ($canManage)
        <x-slot:actions><a href="{{ route('payroll.tax-tables.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New tax table') }}</a></x-slot:actions>
    @endif
    @forelse ($records as $record)
        <x-card :title="$record->name" icon="bi-percent" data-tax-table="{{ $record->tax_year }}">
            <div class="row">
                <div class="col-lg-4">
                    <div class="fw-semibold small mb-1">{{ __('Tax-free amount a year') }}</div>
                    <ul class="list-unstyled mb-3">@foreach ($record->thresholds as $row)<li>{{ $row['label'] }}: <span class="font-monospace">{{ number_format((float) $row['amount'], 2) }}</span></li>@endforeach</ul>
                    <div class="fw-semibold small mb-1">{{ __('Minimum tax a year') }}</div>
                    <ul class="list-unstyled mb-0">@forelse ($record->minimum_tax as $row)<li>{{ $row['label'] }}: <span class="font-monospace">{{ number_format((float) $row['amount'], 2) }}</span></li>@empty<li>—</li>@endforelse</ul>
                </div>
                <div class="col-lg-4">
                    <div class="fw-semibold small mb-1">{{ __('Slabs above the tax-free amount') }}</div>
                    <table class="table table-sm mb-3"><tbody>
                        @foreach ($record->slabs as $slab)<tr><td>{{ $slab->width !== null ? __('Next :amount', ['amount' => number_format((float) $slab->width, 0)]) : __('The rest') }}</td><td class="text-end font-monospace">{{ rtrim(rtrim((string) $slab->rate, '0'), '.') }}%</td></tr>@endforeach
                    </tbody></table>
                </div>
                <div class="col-lg-4">
                    <dl class="mb-0">
                        <dt>{{ __('Salary exemption') }}</dt><dd>{{ $record->exemption_fraction }} {{ __('of salary, up to :cap', ['cap' => number_format((float) $record->exemption_cap, 2)]) }}</dd>
                        <dt>{{ __('From') }}</dt><dd>{{ $record->effective_from->format('d M Y') }} · {{ $record->tax_year }}</dd>
                        <dt>{{ __('Status') }}</dt><dd>{{ $record->is_active ? __('Active') : __('Inactive') }}</dd>
                    </dl>
                    @if ($canManage)
                        <a href="{{ route('payroll.tax-tables.edit', $record) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                        <x-confirm-delete :action="route('payroll.tax-tables.destroy', $record)" :text="__('":name" will be removed.', ['name' => $record->name])" />
                    @endif
                </div>
            </div>
        </x-card>
    @empty
        <x-empty-state :title="__('No income tax tables yet')" />
    @endforelse
</x-layouts::app>
