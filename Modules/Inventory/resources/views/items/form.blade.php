{{--
    An item (Step 5.2): identity and units, tracking, reorder defaults, its own unit conversions (1 box = 24 pcs) and its
    par and reorder levels in each store. The server checks every rule again.
--}}
<x-layouts::app :title="$item ? $item->name : __('New item')" :subtitle="$item?->sku" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Items') => route('inventory.items.index'), ($item?->sku ?? __('New')) => null]">
    <form method="POST" action="{{ $item ? route('inventory.items.update', $item) : route('inventory.items.store') }}" data-item-form
        x-data="{ conversions: @js($conversions), levels: @js($levels), stockable: @js((bool) old('is_stockable', $item?->is_stockable ?? true)) }">
        @csrf
        @if ($item) @method('PUT') @endif

        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Item')" icon="bi-box-seam">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="sku" :label="__('SKU')" :value="old('sku', $item?->sku)" :help="__('Leave blank to number it automatically (ITM-00001).')" /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="old('name', $item?->name)" required /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="item_category_id" :label="__('Category')" :options="$categories" :value="old('item_category_id', $item?->item_category_id)" :placeholder="__('Choose a category')" required /></div>
                        <div class="col-md-6"><x-form.input name="barcode" :label="__('Barcode')" :value="old('barcode', $item?->barcode)" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><x-form.select name="base_unit_id" :label="__('Base unit')" :options="$units" :value="old('base_unit_id', $item?->base_unit_id)" :placeholder="__('Choose a unit')" required /></div>
                        <div class="col-md-4"><x-form.select name="purchase_unit_id" :label="__('Purchase unit')" :options="$units" :value="old('purchase_unit_id', $item?->purchase_unit_id)" :placeholder="__('Same as base')" /></div>
                        <div class="col-md-4"><x-form.select name="issue_unit_id" :label="__('Issue unit')" :options="$units" :value="old('issue_unit_id', $item?->issue_unit_id)" :placeholder="__('Same as base')" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="reorder_level" type="number" step="0.0001" min="0" :label="__('Reorder level')" :value="old('reorder_level', $item ? rtrim(rtrim((string) $item->reorder_level, '0'), '.') : '')" /></div>
                        <div class="col-md-4"><x-form.input name="reorder_qty" type="number" step="0.0001" min="0" :label="__('Reorder quantity')" :value="old('reorder_qty', $item ? rtrim(rtrim((string) $item->reorder_qty, '0'), '.') : '')" /></div>
                        <div class="col-md-4"><x-form.input name="standard_cost" type="number" step="0.0001" min="0" :label="__('Standard cost (per base unit)')" :value="old('standard_cost', $item?->standard_cost !== null ? rtrim(rtrim((string) $item->standard_cost, '0'), '.') : '')" /></div>
                    </div>
                    <x-form.input name="description" :label="__('Notes')" :value="old('description', $item?->description)" />
                    @foreach (['is_stockable' => [__('Stocked item'), true], 'track_batches' => [__('Track batches'), false], 'track_expiry' => [__('Track expiry dates'), false], 'is_active' => [__('Active'), true]] as $field => [$label, $default])
                        <input type="hidden" name="{{ $field }}" value="0">
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="item-{{ $field }}" name="{{ $field }}" value="1" @checked(old($field, $item?->{$field} ?? $default))><label class="form-check-label" for="item-{{ $field }}">{{ $label }}</label></div>
                    @endforeach
                </x-card>

                <x-card :title="__('Conversions of this item')" icon="bi-arrow-left-right">
                    <p class="small text-body-secondary">{{ __('How many base units one box, sack or carton of this item holds. Weights and volumes convert by themselves (kg to g).') }}</p>
                    <template x-for="(row, index) in conversions" :key="index">
                        <div class="row g-2 align-items-end mb-2" data-conversion>
                            <div class="col-md-5"><select class="form-select form-select-sm" :name="`conversions[${index}][unit_id]`" x-model="row.unit_id" aria-label="{{ __('Unit') }}" required>
                                <option value="">{{ __('Choose a unit') }}</option>@foreach ($units as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-5"><input type="number" step="0.000001" min="0.000001" class="form-control form-control-sm text-end" :name="`conversions[${index}][factor]`" x-model="row.factor" placeholder="{{ __('base units in one') }}" aria-label="{{ __('Base units in one') }}" required></div>
                            <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-danger" @click="conversions.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="conversions.push({ unit_id: '', factor: '' })" data-add-conversion><i class="bi bi-plus-lg"></i> {{ __('Add conversion') }}</button>
                </x-card>

                <x-card :title="__('Levels per store')" icon="bi-shop">
                    <p class="small text-body-secondary">{{ __('Par is the stock to keep; a store\'s reorder level replaces the item\'s own there.') }}</p>
                    <template x-for="(row, index) in levels" :key="index">
                        <div class="row g-2 align-items-end mb-2" data-level>
                            <div class="col-md-5"><select class="form-select form-select-sm" :name="`levels[${index}][store_id]`" x-model="row.store_id" aria-label="{{ __('Store') }}" required>
                                <option value="">{{ __('Choose a store') }}</option>@foreach ($stores as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-3"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`levels[${index}][par_level]`" x-model="row.par_level" placeholder="{{ __('Par') }}" aria-label="{{ __('Par level') }}"></div>
                            <div class="col-md-3"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`levels[${index}][reorder_level]`" x-model="row.reorder_level" placeholder="{{ __('Reorder at') }}" aria-label="{{ __('Reorder level') }}"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="levels.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="levels.push({ store_id: '', par_level: '', reorder_level: '' })" data-add-level><i class="bi bi-plus-lg"></i> {{ __('Add store') }}</button>
                </x-card>

                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save item') }}</button>
                    <a href="{{ route('inventory.items.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
                </div>
            </div>
            <div class="col-xl-4">@if ($item)<x-audit-trail :entries="$trail" />@endif</div>
        </div>
    </form>
</x-layouts::app>
