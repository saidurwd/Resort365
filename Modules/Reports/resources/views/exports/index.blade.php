<x-layouts::app :title="__('My files')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reports') => route('reports.index'), __('My files') => null]">
    <p class="text-body-secondary">{{ __('Excel and PDF reports are made in the background. You get a notification when one is ready; files are kept for a week.') }}</p>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-exports>
            <thead><tr><th class="ps-3">{{ __('Report') }}</th><th>{{ __('File') }}</th><th>{{ __('Asked') }}</th><th>{{ __('Status') }}</th><th class="text-end pe-3"></th></tr></thead>
            <tbody>
                @forelse ($exports as $export)
                    <tr data-export="{{ $export->id }}">
                        <td class="ps-3">{{ $export->title }}<div class="small text-body-secondary">{{ $export->filters['from'] ?? '' }} → {{ $export->filters['to'] ?? '' }}</div></td>
                        <td><x-status-badge :status="$export->format" /></td>
                        <td>{{ $export->created_at->inPropertyTime()->format('d M Y H:i') }}</td>
                        <td><x-status-badge :status="$export->status" />@if ($export->error)<div class="small text-danger">{{ $export->error }}</div>@endif</td>
                        <td class="text-end pe-3">
                            @if ($export->status === \Modules\Reports\Enums\ExportStatus::Ready && $export->path && $export->expires_at?->isFuture())
                                <a href="{{ route('reports.exports.download', $export->id) }}" class="btn btn-sm btn-outline-primary" data-download><i class="bi bi-download"></i> {{ __('Download') }}</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No files yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
