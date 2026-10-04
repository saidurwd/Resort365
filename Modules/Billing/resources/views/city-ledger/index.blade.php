@php
    $money = fn (string $amount): string => number_format((float) $amount, 2);
    $labels = ['current' => __('Not due'), '1_30' => __('1–30 days'), '31_60' => __('31–60 days'), '61_90' => __('61–90 days'), 'over_90' => __('Over 90 days')];
@endphp

<x-layouts::app :title="__('City ledger')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('City ledger') => null]">
    <x-card :title="__('Aging')" icon="bi-hourglass-split" body-class="p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0" data-aging>
                <thead><tr><th class="ps-3">{{ __('Company') }}</th>@foreach ($buckets as $bucket)<th class="text-end">{{ $labels[$bucket] }}</th>@endforeach<th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                <tbody>
                    @forelse ($companies as $row)
                        <tr data-company="{{ $row['company']?->name }}">
                            <td class="ps-3 fw-semibold">{{ $row['company']?->name }}</td>
                            @foreach ($buckets as $bucket)<td class="text-end font-monospace">{{ $row['aging'][$bucket] === '0.00' ? '' : $money($row['aging'][$bucket]) }}</td>@endforeach
                            <td class="text-end pe-3 font-monospace fw-semibold">{{ $money($row['aging']['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ps-3 text-body-secondary">{{ __('Nothing is owed on account.') }}</td></tr>
                    @endforelse
                </tbody>
                @if ($companies->isNotEmpty())
                    <tfoot><tr class="fw-semibold"><td class="ps-3">{{ __('Total') }}</td>@foreach ($buckets as $bucket)<td class="text-end font-monospace">{{ $money($totals[$bucket]) }}</td>@endforeach<td class="text-end pe-3 font-monospace">{{ $money($totals['total']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-card>

    @foreach ($companies as $row)
        <x-card :title="$row['company']?->name" icon="bi-building" body-class="p-0">
            <table class="table table-sm align-middle mb-0" data-ledger-entries>
                <thead><tr><th class="ps-3">{{ __('Posted') }}</th><th>{{ __('Due') }}</th><th>{{ __('For') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Open') }}</th><th class="pe-3"></th></tr></thead>
                <tbody>
                    @foreach ($row['entries'] as $entry)
                        <tr data-entry="{{ $entry->id }}">
                            <td class="ps-3 text-nowrap">{{ $entry->posted_on->format('d M Y') }}</td>
                            <td class="text-nowrap @if ($entry->due_on->isPast()) text-danger @endif">{{ $entry->due_on->format('d M Y') }}</td>
                            <td>{{ $entry->description }}</td>
                            <td class="text-end font-monospace">{{ $money($entry->amount) }}</td>
                            <td class="text-end font-monospace fw-semibold">{{ $money($entry->open()) }}</td>
                            <td class="pe-3">
                                @can('billing.city-ledger.manage')
                                    <form method="POST" action="{{ route('billing.city-ledger.receive', $entry) }}" class="d-flex gap-1 justify-content-end" data-receive="{{ $entry->id }}">
                                        @csrf
                                        <select name="method" class="form-select form-select-sm w-auto" aria-label="{{ __('Method') }}">@foreach ($methods as $value => $label)<option value="{{ $value }}" @selected($value === 'bank_transfer')>{{ $label }}</option>@endforeach</select>
                                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ $entry->open() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('Amount') }}">
                                        <button class="btn btn-sm btn-outline-success">{{ __('Receive') }}</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endforeach
</x-layouts::app>
