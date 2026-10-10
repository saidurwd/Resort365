{{--
    Accounting → Reconciliation (Step 4.4): import a bank statement CSV, then open it to match its lines to the books.
--}}
<x-layouts::app :title="__('Bank reconciliation')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Reconciliation') => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Statements')" icon="bi-bank" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" data-statements>
                        <thead><tr><th class="ps-3">{{ __('Bank account') }}</th><th>{{ __('Period') }}</th><th>{{ __('File') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Closing balance') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($statements as $statement)
                                <tr data-statement="{{ $statement->id }}">
                                    <td class="ps-3">{{ $statement->bankAccount->name }}</td>
                                    <td>{{ $statement->statement_from->format('d M') }} – {{ $statement->statement_to->format('d M Y') }}</td>
                                    <td class="small text-body-secondary">{{ $statement->file_name }}</td>
                                    <td class="text-end">{{ $statement->line_count }}</td>
                                    <td class="text-end font-monospace">{{ number_format((float) $statement->closing_balance, 2) }}</td>
                                    <td>@if ($statement->reconciliation)<span class="badge text-bg-success">{{ __('Reconciled') }}</span>@else<span class="badge text-bg-warning">{{ __('Open') }}</span>@endif</td>
                                    <td class="text-end pe-3"><a href="{{ route('accounting.reconciliation.show', $statement) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-empty-state :title="__('No statements imported yet')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        @if ($canManage)
            <div class="col-xl-4">
                <x-card :title="__('Import a statement')" icon="bi-upload">
                    <form method="POST" action="{{ route('accounting.reconciliation.import') }}" enctype="multipart/form-data" data-import-form>
                        @csrf
                        <x-form.select name="bank_account_id" :label="__('Bank account')" :options="$banks" :value="old('bank_account_id')" :placeholder="__('Choose an account')" required />
                        <div class="mb-3">
                            <label class="form-label" for="statement-file">{{ __('CSV file') }}</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="statement-file" name="file" accept=".csv,text/csv,text/plain" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">{{ __('A header row with date, description, reference, withdrawal, deposit and (optionally) balance.') }}</div>
                        </div>
                        <x-form.input name="closing_balance" type="number" step="0.01" :label="__('Closing balance (if the file has no balance column)')" :value="old('closing_balance')" />
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Import') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts::app>
