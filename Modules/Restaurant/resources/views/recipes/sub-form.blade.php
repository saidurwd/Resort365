@php($editing = $recipe !== null)
<x-layouts::app :title="$editing ? $recipe->name : __('New sub-recipe')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Sub-recipes') => route('restaurant.sub-recipes.index'), $editing ? $recipe->name : __('New') => null]">
    <form method="POST" action="{{ $editing ? route('restaurant.sub-recipes.update', $recipe) : route('restaurant.sub-recipes.store') }}" data-sub-recipe-form x-data="{ lines: @js($lines) }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-card :title="__('Sub-recipe')" icon="bi-journal-text">
            <div class="row">
                <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="old('name', $recipe?->name)" required /></div>
                <div class="col-md-3"><x-form.input name="yield_quantity" type="number" step="0.0001" :label="__('Makes')" :value="old('yield_quantity', $recipe ? rtrim(rtrim((string) $recipe->yield_quantity, '0'), '.') : '1')" required /></div>
                <div class="col-md-2"><x-form.input name="yield_unit" :label="__('Unit')" :value="old('yield_unit', $recipe?->yield_unit)" :help="__('kg, l, portion…')" /></div>
                <div class="col-md-12"><x-form.input name="notes" :label="__('Notes')" :value="old('notes', $recipe?->notes)" /></div>
            </div>
            <template x-for="(row, index) in lines" :key="index">
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4"><select class="form-select form-select-sm" :name="`lines[${index}][item_id]`" x-model="row.item_id" @change="if (row.item_id) row.sub_recipe_id = ''" aria-label="{{ __('Ingredient') }}"><option value="">{{ __('Ingredient') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-3"><select class="form-select form-select-sm" :name="`lines[${index}][sub_recipe_id]`" x-model="row.sub_recipe_id" @change="if (row.sub_recipe_id) row.item_id = ''" aria-label="{{ __('or a sub-recipe') }}"><option value="">{{ __('or a sub-recipe') }}</option>@foreach ($subRecipes as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-2"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`lines[${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}"></div>
                    <div class="col-md-2"><input type="number" step="0.01" min="0" max="99.99" class="form-control form-control-sm text-end" :name="`lines[${index}][waste_percent]`" x-model="row.waste_percent" placeholder="{{ __('Waste %') }}" aria-label="{{ __('Waste %') }}"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="lines.splice(index, 1)" :disabled="lines.length < 2" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="lines.push({ item_id: '', sub_recipe_id: '', quantity: '', waste_percent: '' })" data-add-row><i class="bi bi-plus-lg"></i> {{ __('Add ingredient') }}</button>
        </x-card>
        <div class="d-flex gap-2">
            @if ($canManage)<button type="submit" class="btn btn-primary" data-save-sub-recipe>{{ __('Save') }}</button>@endif
            <a href="{{ route('restaurant.sub-recipes.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
        </div>
    </form>
    @if ($editing && $canManage)
        <form method="POST" action="{{ route('restaurant.sub-recipes.destroy', $recipe) }}" class="mt-3" data-confirm="{{ __('Delete this sub-recipe?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger" data-delete-sub-recipe><i class="bi bi-trash"></i> {{ __('Delete') }}</button></form>
    @endif
</x-layouts::app>
