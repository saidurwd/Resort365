@php
    $variants = old('variants', $item?->variants->pluck('name')->all() ?? []);
    $components = old('components', $item?->components->map(fn ($component) => ['item_id' => $component->component_item_id, 'variant_id' => $component->component_variant_id, 'quantity' => $component->quantity])->all() ?? []);
    $title = $item ? $item->code.' · '.$item->translated('name') : __('New menu item');
@endphp
<x-layouts::app :title="$title" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Menu') => route('restaurant.menu.items.index'), ($item?->code ?? __('New')) => null]">
    <form method="POST" action="{{ $item ? route('restaurant.menu.items.update', $item) : route('restaurant.menu.items.store') }}" data-menu-item-form
        x-data="{ kind: @js(old('kind', $item?->kind->value ?? 'dish')), variants: @js(array_values($variants)), components: @js(array_values($components)) }">
        @csrf
        @if ($item) @method('PUT') @endif
        <fieldset class="border-0 p-0 m-0" @disabled(! $canManage)>
        <div class="row">
            <div class="col-xl-7">
                <x-card :title="__('Item')" icon="bi-egg-fried">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code (PLU)')" :value="old('code', $item?->code)" required /></div>
                        <div class="col-md-8"><x-form.select name="menu_category_id" :label="__('Category')" :options="$categories" :value="old('menu_category_id', $item?->menu_category_id)" required /></div>
                    </div>
                    @foreach ($languages as $code => $language)
                        <x-form.input :name="'name['.$code.']'" :id="'field-name-'.$code" :label="__('Name').' ('.$language.')'" :value="old('name.'.$code, $item?->name[$code] ?? '')" :required="$code === 'en'" />
                    @endforeach
                    @foreach ($languages as $code => $language)
                        <x-form.field :name="'description['.$code.']'" :id="'field-description-'.$code" :label="__('Description').' ('.$language.')'">
                            <textarea name="description[{{ $code }}]" id="field-description-{{ $code }}" rows="2" class="form-control">{{ old('description.'.$code, $item?->description[$code] ?? '') }}</textarea>
                        </x-form.field>
                    @endforeach
                    <div class="row">
                        <div class="col-md-4"><x-form.select name="course" :label="__('Course')" :options="$courses" :value="old('course', $item?->course->value ?? 'main')" :search="false" required /></div>
                        <div class="col-md-4">
                            <x-form.field name="kind" :label="__('Kind')" required>
                                <select name="kind" id="field-kind" class="form-select" x-model="kind">
                                    @foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </x-form.field>
                        </div>
                        <div class="col-md-4"><x-form.select name="tax_category_id" :label="__('Tax category')" :options="$taxCategories" :value="old('tax_category_id', $item?->tax_category_id)" :placeholder="__('The outlet\'s')" /></div>
                    </div>
                    <p class="small text-body-secondary" x-show="kind === 'open'" x-cloak>{{ __('An open item is priced by the staff member on the POS (with permission).') }}</p>
                    <p class="small text-body-secondary" x-show="kind === 'direct_stock'" x-cloak>{{ __('Sold as bought (bottled drinks, snacks); linked to its inventory item once Inventory is set up.') }}</p>
                    <div class="row">
                        <div class="col-md-6">
                            <x-form.field name="dietary_tags" :label="__('Dietary')">
                                @foreach ($dietaryTags as $value => $label)
                                    <div class="form-check"><input type="checkbox" class="form-check-input" name="dietary_tags[]" value="{{ $value }}" id="tag-{{ $value }}" @checked(in_array($value, old('dietary_tags', $item?->dietary_tags ?? []), true))><label class="form-check-label" for="tag-{{ $value }}">{{ $label }}</label></div>
                                @endforeach
                            </x-form.field>
                        </div>
                        <div class="col-md-6">
                            <x-form.field name="allergens" :label="__('Allergens')">
                                <div class="row">
                                    @foreach ($allergens as $value => $label)
                                        <div class="col-6 form-check"><input type="checkbox" class="form-check-input" name="allergens[]" value="{{ $value }}" id="allergen-{{ $value }}" @checked(in_array($value, old('allergens', $item?->allergens ?? []), true))><label class="form-check-label" for="allergen-{{ $value }}">{{ $label }}</label></div>
                                    @endforeach
                                </div>
                            </x-form.field>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Order in category')" :value="old('sort_order', $item?->sort_order ?? 0)" /></div>
                        <div class="col-md-8 pt-md-4">
                            <div class="form-check mt-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="form-check-input" name="is_active" value="1" id="field-is_active" @checked(old('is_active', $item?->is_active ?? true))>
                                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>
            <div class="col-xl-5">
                <x-card :title="__('Variants')" icon="bi-diagram-2" x-show="kind !== 'combo'">
                    <p class="small text-body-secondary">{{ __('E.g. Half and Full, or Glass and Bottle: each is priced separately in every outlet. Leave empty for one price.') }}</p>
                    <template x-for="(variant, index) in variants" :key="index">
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" name="variants[]" class="form-control" x-model="variants[index]" maxlength="60" :aria-label="'{{ __('Variant') }} ' + (index + 1)">
                            <button type="button" class="btn btn-outline-danger" @click="variants.splice(index, 1)" aria-label="{{ __('Remove variant') }}"><i class="bi bi-x"></i></button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="variants.push('')" data-add-variant><i class="bi bi-plus"></i> {{ __('Add variant') }}</button>
                </x-card>
                <x-card :title="__('Combo contents')" icon="bi-collection" x-show="kind === 'combo'" x-cloak>
                    <template x-for="(component, index) in components" :key="index">
                        <div class="d-flex gap-2 mb-2">
                            <select class="form-select form-select-sm" :value="component.item_id + ':' + (component.variant_id ?? '')"
                                @change="[component.item_id, component.variant_id] = $event.target.value.split(':'); component.variant_id = component.variant_id || null" :aria-label="'{{ __('Combo item') }} ' + (index + 1)">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($comboItems as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            <input type="hidden" :name="'components[' + index + '][item_id]'" :value="component.item_id">
                            <input type="hidden" :name="'components[' + index + '][variant_id]'" :value="component.variant_id ?? ''">
                            <input type="number" min="1" max="20" class="form-control form-control-sm combo-quantity" :name="'components[' + index + '][quantity]'" x-model="component.quantity" aria-label="{{ __('Quantity') }}">
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="components.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-outline-primary" @click="components.push({ item_id: '', variant_id: null, quantity: 1 })"><i class="bi bi-plus"></i> {{ __('Add item') }}</button>
                </x-card>
                <x-card :title="__('Modifiers')" icon="bi-sliders">
                    <x-form.select name="modifier_group_ids[]" id="field-modifier_group_ids" :label="__('Modifier groups')" :options="$groups"
                        :value="old('modifier_group_ids', $item?->modifierGroups->pluck('id')->all() ?? [])" multiple :help="__('Asked in this order on the POS.')" />
                </x-card>
            </div>
        </div>
        </fieldset>
        @if ($canManage)
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save item') }}</button>
        @endif
    </form>
    @if ($item)
        <div class="row mt-4"><div class="col-xl-7"><x-photo-gallery :subject="$item" :title="__('Photo for the POS')" /></div></div>
    @endif
</x-layouts::app>
