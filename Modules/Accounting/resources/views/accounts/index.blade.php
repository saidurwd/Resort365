{{--
    Accounting → Chart of accounts (ARCHITECTURE §5.14): the tree of ledger accounts. Alpine fills the shared
    edit form from the account's row; the server checks every rule again.
--}}
<x-layouts::app :title="__('Chart of accounts')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Chart of accounts') => null]">
    <x-slot:actions>
        @if ($canManage)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#account-modal" data-new-account><i class="bi bi-plus-lg"></i> {{ __('Add account') }}</button>
        @endif
    </x-slot:actions>

    <div x-data="{
        action: @js(route('accounting.accounts.store')), method: 'POST', title: @js(__('Add account')),
        account: { code: '', name: '', type: 'asset', parent_id: '', is_group: false, is_active: true, usali_department: '', description: '' },
        blank() { this.action = @js(route('accounting.accounts.store')); this.method = 'POST'; this.title = @js(__('Add account')); this.account = { code: '', name: '', type: 'asset', parent_id: '', is_group: false, is_active: true, usali_department: '', description: '' }; },
        edit(account, url) { this.action = url; this.method = 'PUT'; this.title = @js(__('Change account')); this.account = { ...account }; },
    }">
        <x-card body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" data-chart>
                    <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th>{{ __('Department') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @include('accounting::accounts.rows', ['parent' => 0, 'depth' => 0])
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($canManage)
            <div class="modal fade" id="account-modal" tabindex="-1" aria-labelledby="account-modal-title" aria-hidden="true" @if ($errors->any()) data-show-on-load @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" :action="action" class="modal-content" data-account-form>
                        @csrf
                        <input type="hidden" name="_method" :value="method">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="account-modal-title" x-text="title"></h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-4"><label class="form-label" for="account-code">{{ __('Code') }} <span class="text-danger">*</span></label><input type="text" id="account-code" name="code" class="form-control" x-model="account.code" required maxlength="20"></div>
                                <div class="col-8"><label class="form-label" for="account-name">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" id="account-name" name="name" class="form-control" x-model="account.name" required maxlength="190"></div>
                                <div class="col-6">
                                    <label class="form-label" for="account-type">{{ __('Type') }}</label>
                                    <select id="account-type" name="type" class="form-select" x-model="account.type">@foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="account-parent">{{ __('Inside group') }}</label>
                                    <select id="account-parent" name="parent_id" class="form-select" x-model="account.parent_id"><option value="">{{ __('Top level') }}</option>@foreach ($groups as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
                                </div>
                                <div class="col-12"><label class="form-label" for="account-usali">{{ __('USALI department') }}</label><input type="text" id="account-usali" name="usali_department" class="form-control" x-model="account.usali_department" maxlength="40" placeholder="rooms, fnb, admin…"></div>
                                <div class="col-12"><label class="form-label" for="account-description">{{ __('Description') }}</label><input type="text" id="account-description" name="description" class="form-control" x-model="account.description" maxlength="300"></div>
                                <div class="col-12 d-flex gap-4">
                                    <div class="form-check"><input type="hidden" name="is_group" value="0"><input type="checkbox" class="form-check-input" id="account-group" name="is_group" value="1" x-model="account.is_group"><label class="form-check-label" for="account-group">{{ __('A group (header, takes no postings)') }}</label></div>
                                    <div class="form-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" class="form-check-input" id="account-active" name="is_active" value="1" x-model="account.is_active"><label class="form-check-label" for="account-active">{{ __('Active') }}</label></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
