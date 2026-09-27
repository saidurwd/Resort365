<x-layouts::app :title="__('Document numbering')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Document numbering') => null]">
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">{{ __('Document') }}</th>
                        <th>{{ __('Format') }}</th>
                        <th>{{ __('Resets') }}</th>
                        <th>{{ __('Next number') }}</th>
                        <th class="pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="ps-3 fw-semibold">{{ __($row['type']->label) }}</td>
                            <td><code>{{ $row['format'] }}</code></td>
                            <td><x-status-badge :status="$row['reset']" /></td>
                            <td><span class="font-monospace" data-next-number="{{ $row['type']->key }}">{{ $row['preview'] }}</span></td>
                            <td class="text-end pe-3">
                                @can('core.sequence.update')
                                    <a href="{{ route('core.sequences.edit', $row['type']->key) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <x-slot:footer>
            <span class="small text-body-secondary">{{ __('Tokens: {PREFIX} {YYYY} {YY} {MM} {DD} {SEQ} {SEQ:n} (zero-padded to n digits).') }}</span>
        </x-slot:footer>
    </x-card>
</x-layouts::app>
