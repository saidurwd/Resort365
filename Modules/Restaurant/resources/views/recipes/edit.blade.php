<x-layouts::app :title="__('Recipe: :item', ['item' => $item->translated('name')])" :subtitle="$item->code" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Menu') => route('restaurant.menu.items.index'), $item->translated('name') => route('restaurant.menu.items.edit', $item), __('Recipe') => null]">
    <form method="POST" action="{{ route('restaurant.recipes.update', $item) }}" data-recipe-form
        x-data="{ sections: @js($state), blank() { return { item_id: '', sub_recipe_id: '', quantity: '', waste_percent: '' }; } }">
        @csrf @method('PUT')
        @if ($plates !== [])
            <x-card :title="__('Plate cost of one portion')" icon="bi-calculator" data-plate-costs>
                <div class="row">
                    @foreach ($plates as $outlet => $cost)
                        <div class="col-md-3 mb-2"><div class="small text-body-secondary">{{ $outlet }}</div><div class="fs-5 font-monospace" data-plate="{{ $outlet }}">{{ $cost !== null ? number_format((float) $cost, 2) : '—' }}</div></div>
                    @endforeach
                </div>
                <p class="small text-body-secondary mb-0">{{ __('At the average cost of the outlet\'s store; the standard cost where the store holds none.') }}</p>
            </x-card>
        @endif
        @foreach ($sections as $section)
            <x-card :title="$section['title']" icon="bi-journal-text" data-section="{{ $section['key'] }}">
                <p class="small text-body-secondary">{{ $section['hint'] }}</p>
                <template x-for="(row, index) in sections['{{ $section['key'] }}']" :key="index">
                    <div class="row g-2 align-items-end mb-2" data-recipe-row>
                        <div class="col-md-4"><select class="form-select form-select-sm" :name="`sections[{{ $section['key'] }}][${index}][item_id]`" x-model="row.item_id" @change="if (row.item_id) row.sub_recipe_id = ''" aria-label="{{ __('Ingredient') }}"><option value="">{{ __('Ingredient') }}</option>@foreach ($items as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-3"><select class="form-select form-select-sm" :name="`sections[{{ $section['key'] }}][${index}][sub_recipe_id]`" x-model="row.sub_recipe_id" @change="if (row.sub_recipe_id) row.item_id = ''" aria-label="{{ __('or a sub-recipe') }}"><option value="">{{ __('or a sub-recipe') }}</option>@foreach ($subRecipes as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-2"><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" :name="`sections[{{ $section['key'] }}][${index}][quantity]`" x-model="row.quantity" placeholder="{{ __('Quantity') }}" aria-label="{{ __('Quantity') }}"></div>
                        <div class="col-md-2"><input type="number" step="0.01" min="0" max="99.99" class="form-control form-control-sm text-end" :name="`sections[{{ $section['key'] }}][${index}][waste_percent]`" x-model="row.waste_percent" placeholder="{{ __('Waste %') }}" aria-label="{{ __('Waste %') }}"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="sections['{{ $section['key'] }}'].splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                </template>
                @if ($canManage)<button type="button" class="btn btn-sm btn-outline-primary" @click="sections['{{ $section['key'] }}'].push(blank())" data-add-row><i class="bi bi-plus-lg"></i> {{ __('Add ingredient') }}</button>@endif
            </x-card>
        @endforeach
        <p class="small text-body-secondary">{{ __('Quantities are per portion, in the ingredient\'s base unit. Waste is yield lost in preparation: 10% waste on 90 g uses 100 g. Leave a recipe empty to remove it.') }}</p>
        <div class="d-flex gap-2">@if ($canManage)<button type="submit" class="btn btn-primary" data-save-recipe>{{ __('Save recipe') }}</button>@endif<a href="{{ route('restaurant.menu.items.edit', $item) }}" class="btn btn-outline-secondary">{{ __('Back to the item') }}</a></div>
    </form>
</x-layouts::app>
