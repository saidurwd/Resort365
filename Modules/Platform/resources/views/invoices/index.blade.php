<x-layouts::platform :title="__('Invoices')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Invoices') => null]">
    <x-card :title="__('All invoices')" icon="bi-receipt" body-class="p-0">
        <x-slot:tools>
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                <select name="status" class="form-select form-select-sm w-auto" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Every status') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($status?->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" name="overdue" value="1" id="filter-overdue" @checked($overdue)>
                    <label class="form-check-label" for="filter-overdue">{{ __('Overdue only') }}</label>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Filter') }}</button>
            </form>
        </x-slot:tools>
        @if ($invoices->isEmpty())
            <x-empty-state icon="bi-receipt" :title="__('No invoices')" :message="__('Invoices are issued when a trial ends and each time a subscription renews.')" />
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0" data-invoices>
                    <thead><tr><th class="ps-3">{{ __('Number') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Balance') }}</th><th class="pe-3">{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td class="ps-3"><a href="{{ route('platform.invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                <td><a href="{{ route('platform.tenants.show', $invoice->tenant_id) }}">{{ $invoice->tenant->name }}</a></td>
                                <td>{{ $invoice->issued_on->format('d M Y') }}</td>
                                <td @class(['text-danger fw-semibold' => $invoice->isOverdue()])>{{ $invoice->due_on->format('d M Y') }}</td>
                                <td class="text-end">{{ $invoice->currency_code }} {{ $invoice->amount }}</td>
                                <td class="text-end">{{ $invoice->balance() }}</td>
                                <td class="pe-3"><x-status-badge :status="$invoice->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $invoices->links() }}</div>
        @endif
    </x-card>
</x-layouts::platform>
