<x-layouts::app :title="__('Reconciliation report')" :subtitle="$statement->bankAccount->name.' · '.$statement->statement_to->format('d M Y')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Reconciliation') => route('accounting.reconciliation.index'), __('Report') => null]">
    <x-slot:actions>
        <a href="{{ route('accounting.reconciliation.report', [$statement, 'export' => 'csv']) }}" class="btn btn-outline-secondary" data-export><i class="bi bi-download"></i> {{ __('CSV') }}</a>
        <a href="{{ route('accounting.reconciliation.show', $statement) }}" class="btn btn-outline-secondary">{{ __('Back to the statement') }}</a>
    </x-slot:actions>

    <div class="row">
        <div class="col-lg-5">
            <x-card :title="$complete ? __('Reconciled') : __('Not reconciled yet')" icon="bi-calculator">
                @include('accounting::banking.reconciliation.figures', ['figures' => $figures])
            </x-card>
        </div>
        <div class="col-lg-7">
            <x-card :title="__('Outstanding in the books')" icon="bi-hourglass-split" body-class="p-0">
                <table class="table table-sm mb-0" data-outstanding>
                    <tbody>
                        @forelse ($open as $line)
                            <tr><td class="ps-3">{{ $line->entry->entry_date->format('d M Y') }}</td><td>{{ $line->entry->entry_no }} · {{ $line->description ?? $line->entry->description }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) \Modules\Accounting\Services\BankLedger::signed($line), 2) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3">{{ __('Nothing outstanding.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
            <x-card :title="__('Statement lines not matched')" icon="bi-exclamation-triangle" body-class="p-0">
                <table class="table table-sm mb-0" data-unmatched>
                    <tbody>
                        @forelse ($lines->filter(fn ($line) => $line->match === null) as $line)
                            <tr><td class="ps-3">{{ $line->txn_date->format('d M Y') }}</td><td>{{ $line->description }}</td><td class="text-end font-monospace pe-3">{{ number_format((float) $line->signedAmount(), 2) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3">{{ __('Every line is matched.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts::app>
