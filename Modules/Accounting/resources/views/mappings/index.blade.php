{{--
    Accounting → Account mapping (ARCHITECTURE §7.1): which ledger account each automatic posting uses.
    Leaving a row on "Default" follows the chart's system account shown beside it.
--}}
<x-layouts::app :title="__('Account mapping')" :subtitle="__('Where automatic postings go')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Account mapping') => null]">
    <form method="POST" action="{{ route('accounting.mappings.update') }}" data-mapping-form>
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $rows)
            <x-card :title="$group" icon="bi-diagram-2" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th class="ps-3">{{ __('Posting') }}</th><th style="min-width: 22rem">{{ __('Account') }}</th><th class="pe-3">{{ __('Default') }}</th></tr></thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr data-mapping="{{ $row['key'] }}">
                                    <td class="ps-3">{{ $row['label'] }}</td>
                                    <td>
                                        <select class="form-select form-select-sm" name="mappings[{{ $row['key'] }}]" aria-label="{{ $row['label'] }}" @disabled(! $canManage)>
                                            <option value="">{{ __('Default') }}</option>
                                            @foreach ($accounts as $id => $label)
                                                <option value="{{ $id }}" @selected((int) old('mappings.'.$row['key'], $row['account_id']) === $id)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="pe-3 text-body-secondary small">{{ $row['default_label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endforeach

        @if ($canManage)
            <button type="submit" class="btn btn-primary" data-save-mappings><i class="bi bi-save"></i> {{ __('Save mapping') }}</button>
        @endif
    </form>
</x-layouts::app>
