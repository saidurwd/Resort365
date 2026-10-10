{{--
    Reconciling one statement (Step 4.4): matched pairs, statement lines still to match (with the ledger lines of the
    same amount to choose from), ledger lines the bank has not shown, and the figures.
--}}
<x-layouts::app :title="$statement->bankAccount->name" :subtitle="__('Statement to :date', ['date' => $statement->statement_to->format('d M Y')])" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Reconciliation') => route('accounting.reconciliation.index'), $statement->bankAccount->name => null]">
    <x-slot:actions>
        <a href="{{ route('accounting.reconciliation.report', $statement) }}" class="btn btn-outline-secondary" data-report><i class="bi bi-file-earmark-text"></i> {{ __('Report') }}</a>
        @if (! $complete && $canReconcile)
            <form method="POST" action="{{ route('accounting.reconciliation.suggest', $statement) }}" class="d-inline">@csrf
                <button type="submit" class="btn btn-outline-primary" data-suggest><i class="bi bi-magic"></i> {{ __('Match what fits') }}</button></form>
            <form method="POST" action="{{ route('accounting.reconciliation.complete', $statement) }}" class="d-inline" data-confirm="{{ __('Complete the reconciliation? Its matches are locked afterwards.') }}">@csrf
                <button type="submit" class="btn btn-success" data-complete @disabled(! $canComplete)><i class="bi bi-check2-circle"></i> {{ __('Complete') }}</button></form>
        @endif
    </x-slot:actions>

    @if ($complete)
        <div class="alert alert-success" data-reconciled><i class="bi bi-check-circle"></i> {{ __('Reconciled on :date by completing this statement.', ['date' => $statement->reconciliation?->completed_at->inPropertyTime()->format('d M Y H:i')]) }}</div>
    @endif

    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Statement lines')" icon="bi-list-check" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-statement-lines>
                        <thead><tr><th class="ps-3">{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Withdrawal') }}</th><th class="text-end">{{ __('Deposit') }}</th><th class="pe-3">{{ __('Matched to') }}</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr data-line="{{ $line->id }}" class="{{ $line->match ? '' : 'table-warning' }}">
                                    <td class="ps-3">{{ $line->txn_date->format('d M') }}</td>
                                    <td>{{ $line->description }} @if ($line->reference)<span class="text-body-secondary small">{{ $line->reference }}</span>@endif</td>
                                    <td class="text-end font-monospace">{{ (float) $line->withdrawal > 0 ? number_format((float) $line->withdrawal, 2) : '' }}</td>
                                    <td class="text-end font-monospace">{{ (float) $line->deposit > 0 ? number_format((float) $line->deposit, 2) : '' }}</td>
                                    <td class="pe-3">
                                        @if ($line->match)
                                            <a href="{{ route('accounting.journals.show', $line->match->journalLine->entry) }}">{{ $line->match->journalLine->entry->entry_no }}</a>
                                            @if (! $complete && $canReconcile)
                                                <form method="POST" action="{{ route('accounting.reconciliation.unmatch', $line->match) }}" class="d-inline">@csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0 ms-1" data-unmatch title="{{ __('Remove match') }}" aria-label="{{ __('Remove match') }}"><i class="bi bi-x-circle"></i></button></form>
                                            @endif
                                        @elseif ($canReconcile && ! $complete)
                                            @if (($candidates[$line->id] ?? []) === [])
                                                <span class="text-body-secondary small">{{ __('No ledger line of this amount') }}</span>
                                            @else
                                                <form method="POST" action="{{ route('accounting.reconciliation.match') }}" class="d-flex gap-1" data-match-form>@csrf
                                                    <input type="hidden" name="statement_line_id" value="{{ $line->id }}">
                                                    <select name="journal_line_id" class="form-select form-select-sm" aria-label="{{ __('Ledger line') }}">
                                                        @foreach ($candidates[$line->id] as $option)<option value="{{ $option->id }}">{{ $option->entry->entry_date->format('d M') }} · {{ $option->entry->entry_no }} · {{ \Illuminate\Support\Str::limit($option->entry->description, 40) }}</option>@endforeach
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" data-match>{{ __('Match') }}</button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="text-body-secondary small">{{ __('not matched') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <x-card :title="__('In the books, not on the statement')" icon="bi-hourglass-split" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-open-lines>
                        <thead><tr><th class="ps-3">{{ __('Date') }}</th><th>{{ __('Entry') }}</th><th>{{ __('Description') }}</th><th class="text-end pe-3">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @forelse ($open as $line)
                                <tr data-open="{{ $line->id }}">
                                    <td class="ps-3">{{ $line->entry->entry_date->format('d M') }}</td>
                                    <td><a href="{{ route('accounting.journals.show', $line->entry) }}">{{ $line->entry->entry_no }}</a></td>
                                    <td>{{ $line->description ?? $line->entry->description }}</td>
                                    <td class="text-end font-monospace pe-3">{{ number_format((float) \Modules\Accounting\Services\BankLedger::signed($line), 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nothing outstanding.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Reconciliation')" icon="bi-calculator">
                @include('accounting::banking.reconciliation.figures', ['figures' => $figures])
            </x-card>
        </div>
    </div>
</x-layouts::app>
