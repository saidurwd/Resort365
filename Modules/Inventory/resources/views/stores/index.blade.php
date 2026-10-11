<x-layouts::app :title="__('Stores')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stores') => null]">
    <x-slot:actions>
        @if ($canManage)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#store-modal" data-new-store><i class="bi bi-plus-lg"></i> {{ __('Add store') }}</button>@endif
    </x-slot:actions>
    <div x-data="{
        action: @js(route('inventory.stores.store')), method: 'POST', title: @js(__('Add store')), store: { code: '', name: '', kind: 'main', department_id: '', is_active: true },
        blank() { this.action = @js(route('inventory.stores.store')); this.method = 'POST'; this.title = @js(__('Add store')); this.store = { code: '', name: '', kind: 'main', department_id: '', is_active: true }; },
        edit(store, url) { this.action = url; this.method = 'PUT'; this.title = @js(__('Change store')); this.store = { ...store }; },
    }">
        <x-card body-class="p-0">
            <table class="table table-sm table-hover align-middle mb-0" data-stores>
                <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Store') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Department') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($stores as $store)
                        <tr data-store="{{ $store->code }}">
                            <td class="ps-3 font-monospace">{{ $store->code }}</td><td>{{ $store->name }} @unless ($store->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless</td>
                            <td><x-status-badge :status="$store->kind" /></td><td>{{ $departments[$store->department_id] ?? '' }}</td>
                            <td class="text-end pe-3">@if ($canManage)<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#store-modal"
                                @click="edit(@js(['code' => $store->code, 'name' => $store->name, 'kind' => $store->kind->value, 'department_id' => $store->department_id, 'is_active' => $store->is_active]), @js(route('inventory.stores.update', $store)))">{{ __('Change') }}</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state :title="__('No stores yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
        @if ($canManage)
            <div class="modal fade" id="store-modal" tabindex="-1" aria-labelledby="store-modal-title" aria-hidden="true" @if ($errors->any()) data-show-on-load @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" :action="action" class="modal-content" data-store-form>
                        @csrf
                        <input type="hidden" name="_method" :value="method">
                        <div class="modal-header"><h1 class="modal-title fs-5" id="store-modal-title" x-text="title"></h1><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-4 mb-3"><label class="form-label" for="store-code">{{ __('Code') }}</label><input class="form-control" id="store-code" name="code" x-model="store.code" maxlength="20" required></div>
                                <div class="col-8 mb-3"><label class="form-label" for="store-name">{{ __('Name') }}</label><input class="form-control" id="store-name" name="name" x-model="store.name" maxlength="100" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label" for="store-kind">{{ __('Kind') }}</label><select class="form-select" id="store-kind" name="kind" x-model="store.kind">@foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="mb-3"><label class="form-label" for="store-department">{{ __('Department') }}</label><select class="form-select" id="store-department" name="department_id" x-model="store.department_id"><option value="">{{ __('None') }}</option>@foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
                            <input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" id="store-active" name="is_active" value="1" x-model="store.is_active"><label class="form-check-label" for="store-active">{{ __('Active') }}</label></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
