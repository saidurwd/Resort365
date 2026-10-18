@props(['data', 'name' => 'report'])
{{-- A tabular report: optional summary figures, columns, rows, an optional totals row and a note. See App\Support\Reports\CsvExport. --}}
@if (! empty($data['summary']))
    <div class="row g-2 mb-3" data-report-summary>
        @foreach ($data['summary'] as $figure)
            <div class="col-6 col-md-3 col-xl-2"><div class="border rounded p-2 h-100"><div class="small text-body-secondary">{{ $figure['label'] }}</div><div class="fs-5 fw-semibold font-monospace" data-figure="{{ $figure['label'] }}">{{ $figure['value'] }}</div></div></div>
        @endforeach
    </div>
@endif
<x-card :title="$data['title']" icon="bi-bar-chart" body-class="p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" data-report-table="{{ $name }}">
            <thead><tr>@foreach ($data['columns'] as $column)<th class="@if ($loop->first) ps-3 @else text-end @endif">{{ $column }}</th>@endforeach</tr></thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr>@foreach ($row as $cell)<td class="@if ($loop->first) ps-3 @elseif (is_numeric(str_replace([',', '%', ' '], '', (string) $cell))) text-end font-monospace @else text-end @endif">{{ $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($data['columns']) }}"><x-empty-state icon="bi-bar-chart" :title="__('Nothing to report')" :message="__('No records for these filters.')" /></td></tr>
                @endforelse
            </tbody>
            @if (! empty($data['totals']) && $data['rows'] !== [])
                <tfoot><tr class="fw-bold">@foreach ($data['totals'] as $cell)<td class="@if ($loop->first) ps-3 @else text-end font-monospace @endif">{{ $cell }}</td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
</x-card>
@if (! empty($data['note']))<p class="small text-body-secondary mt-2">{{ $data['note'] }}</p>@endif
