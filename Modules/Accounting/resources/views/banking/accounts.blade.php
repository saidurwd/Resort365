{{--
    Accounting → Bank accounts (Step 4.4): cash and bank accounts, each tied to a ledger account. Alpine fills the
    shared form from the row; the server checks every rule again.
--}}
<x-layouts::app :title="__('Bank accounts')" :subtitle="__('Cash and bank')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Bank accounts') => null]">
    <x-slot:actions>
        @if ($canManage)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bank-modal" data-new-bank><i class="bi bi-plus-lg"></i> {{ __('Add account') }}</button>
        @endif
    </x-slot:actions>

    <div x-data="{
        action: @js(route('accounting.banks.store')), method: 'POST', title: @js(__('Add account')),
        bank: { account_id: '', name: '', kind: 'bank', bank_name: '', account_number: '', is_active: true },
        blank() { this.action = @js(route('accounting.banks.store')); this.method = 'POST'; this.title = @js(__('Add account')); this.bank = { account_id: '', name: '', kind: 'bank', bank_name: '', account_number: '', is_active: true }; },
        edit(bank, url) { this.action = url; this.method = 'PUT'; this.title = @js(__('Change account')); this.bank = { ...bank }; },
    }">
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-banks>
                    <thead><tr><th class="ps-3">{{ __('Name') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Bank') }}</th><th>{{ __('Ledger account') }}</th><th class="text-end">{{ __('Balance today') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($banks as $bank)
                            <tr data-bank="{{ $bank->id }}">
                                <td class="ps-3">{{ $bank->name }} @unless ($bank->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless</td>
                                <td><x-status-badge :status="$bank->kind" /></td>
                                <td>{{ $bank->bank_name }} <span class="text-body-secondary">{{ $bank->account_number }}</span></td>
                                <td>{{ $bank->account->label() }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $balances[$bank->id], 2) }}</td>
                                <td class="text-end pe-3">
                                    @if ($canManage)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bank-modal"
                                            @click="edit(@js(['account_id' => $bank->account_id, 'name' => $bank->name, 'kind' => $bank->kind->value, 'bank_name' => $bank->bank_name, 'account_number' => $bank->account_number, 'is_active' => $bank->is_active]), @js(route('accounting.banks.update', $bank)))">{{ __('Change') }}</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state :title="__('No cash or bank accounts yet')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($canManage)
            <div class="modal fade" id="bank-modal" tabindex="-1" aria-labelledby="bank-modal-title" aria-hidden="true" @if ($errors->any()) data-show-on-load @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" :action="action" class="modal-content" data-bank-form>
                        @csrf
                        <input type="hidden" name="_method" :value="method">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="bank-modal-title" x-text="title"></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="bank-account-id">{{ __('Ledger account') }}</label>
                                <select class="form-select" id="bank-account-id" name="account_id" x-model="bank.account_id" required>
                                    <option value="">{{ __('Choose an account') }}</option>
                                    @foreach ($accounts as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div class="mb-3"><label class="form-label" for="bank-name">{{ __('Name') }}</label><input class="form-control" id="bank-name" name="name" x-model="bank.name" maxlength="100" required></div>
                            <div class="mb-3">
                                <label class="form-label" for="bank-kind">{{ __('Kind') }}</label>
                                <select class="form-select" id="bank-kind" name="kind" x-model="bank.kind">
                                    @foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                            <div x-show="bank.kind === 'bank'">
                                <div class="mb-3"><label class="form-label" for="bank-bank-name">{{ __('Bank') }}</label><input class="form-control" id="bank-bank-name" name="bank_name" x-model="bank.bank_name" maxlength="100"></div>
                                <div class="mb-3"><label class="form-label" for="bank-number">{{ __('Account number') }}</label><input class="form-control" id="bank-number" name="account_number" x-model="bank.account_number" maxlength="50"></div>
                            </div>
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="bank-active" name="is_active" value="1" x-model="bank.is_active"><label class="form-check-label" for="bank-active">{{ __('Active') }}</label></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
