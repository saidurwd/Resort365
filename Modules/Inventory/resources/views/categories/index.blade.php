<x-layouts::app :title="__('Item categories')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Categories') => null]">
    <x-slot:actions>
        @if ($canManage)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#category-modal" data-new-category><i class="bi bi-plus-lg"></i> {{ __('Add category') }}</button>@endif
    </x-slot:actions>
    <div x-data="{
        action: @js(route('inventory.categories.store')), method: 'POST', title: @js(__('Add category')), cat: { name: '', parent_id: '', account_class: '', sort_order: 0, is_active: true },
        blank() { this.action = @js(route('inventory.categories.store')); this.method = 'POST'; this.title = @js(__('Add category')); this.cat = { name: '', parent_id: '', account_class: '', sort_order: 0, is_active: true }; },
        edit(cat, url) { this.action = url; this.method = 'PUT'; this.title = @js(__('Change category')); this.cat = { ...cat }; },
    }">
        <x-card body-class="p-0">
            <table class="table table-sm table-hover align-middle mb-0" data-categories>
                <thead><tr><th class="ps-3">{{ __('Category') }}</th><th class="text-end">{{ __('Items') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $category)
                        <tr data-category="{{ $category->id }}">
                            <td class="ps-3">{{ $category->path() }} @if ($category->account_class)<span class="badge text-bg-{{ $category->account_class->color() }}">{{ $category->account_class->label() }}</span>@endif</td><td class="text-end">{{ $category->items_count }}</td>
                            <td>@unless ($category->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless</td>
                            <td class="text-end pe-3">@if ($canManage)<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#category-modal"
                                @click="edit(@js(['name' => $category->name, 'parent_id' => $category->parent_id, 'account_class' => $category->account_class?->value, 'sort_order' => $category->sort_order, 'is_active' => $category->is_active]), @js(route('inventory.categories.update', $category)))">{{ __('Change') }}</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-empty-state :title="__('No categories yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>
        @if ($canManage)
            <div class="modal fade" id="category-modal" tabindex="-1" aria-labelledby="category-modal-title" aria-hidden="true" @if ($errors->any()) data-show-on-load @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" :action="action" class="modal-content" data-category-form>
                        @csrf
                        <input type="hidden" name="_method" :value="method">
                        <div class="modal-header"><h1 class="modal-title fs-5" id="category-modal-title" x-text="title"></h1><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label" for="cat-name">{{ __('Name') }}</label><input class="form-control" id="cat-name" name="name" x-model="cat.name" maxlength="100" required></div>
                            <div class="mb-3"><label class="form-label" for="cat-parent">{{ __('Inside') }}</label><select class="form-select" id="cat-parent" name="parent_id" x-model="cat.parent_id"><option value="">{{ __('Top level') }}</option>@foreach ($options as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="mb-3"><label class="form-label" for="cat-class">{{ __('Stock class (ledger accounts)') }}</label><select class="form-select" id="cat-class" name="account_class" x-model="cat.account_class"><option value="">{{ __('Same as the category it is inside') }}</option>@foreach ($classes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="mb-3"><label class="form-label" for="cat-order">{{ __('Order') }}</label><input type="number" min="0" class="form-control" id="cat-order" name="sort_order" x-model="cat.sort_order"></div>
                            <input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" id="cat-active" name="is_active" value="1" x-model="cat.is_active"><label class="form-check-label" for="cat-active">{{ __('Active') }}</label></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
