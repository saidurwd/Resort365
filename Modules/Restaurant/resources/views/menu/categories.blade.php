<x-layouts::app :title="__('Menu categories')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Menu categories') => null]">
    <div class="row">
        <div @class(['col-xl-8' => $canManage, 'col-12' => ! $canManage])>
            <x-card body-class="p-0">
                @forelse ($options as $id => $path)
                    @php($category = $categories->firstWhere('id', $id))
                    <form method="POST" action="{{ route('restaurant.menu.categories.update', $category) }}" class="d-flex flex-wrap gap-2 align-items-end px-3 py-2 border-bottom" data-category="{{ $category->translated('name', 'en') }}">
                        @csrf @method('PUT')
                        <span class="badge text-bg-{{ $category->colour }} align-self-center">&nbsp;</span>
                        @foreach ($languages as $code => $language)
                            <x-form.input :name="'name['.$code.']'" :id="'category-'.$category->id.'-'.$code" :label="$language" :value="$category->name[$code] ?? ''" wrapper-class="mb-0" :required="$code === 'en'" />
                        @endforeach
                        <x-form.select name="parent_id" :id="'category-parent-'.$category->id" :label="__('Inside')" :options="collect($options)->except($id)->all()" :value="$category->parent_id" :placeholder="__('Top level')" wrapper-class="mb-0" />
                        <x-form.select name="colour" :id="'category-colour-'.$category->id" :label="__('Colour')" :options="$colours" :value="$category->colour" :search="false" wrapper-class="mb-0" />
                        <x-form.select name="revenue_class" :id="'category-class-'.$category->id" :label="__('Sales count as')" :options="$revenueClasses" :value="$category->revenue_class?->value" :placeholder="__('Same as parent (food)')" :search="false" wrapper-class="mb-0" />
                        <x-form.input name="sort_order" type="number" min="0" :id="'category-order-'.$category->id" :label="__('Order')" :value="$category->sort_order" wrapper-class="mb-0 category-order" />
                        <input type="hidden" name="is_active" value="1">
                        <span class="small text-body-secondary mb-2">{{ trans_choice(':count item|:count items', $category->items_count) }}</span>
                        @if ($canManage)<button type="submit" class="btn btn-sm btn-outline-primary mb-1"><i class="bi bi-save"></i></button>@endif
                    </form>
                @empty
                    <x-empty-state icon="bi-diagram-3" :title="__('No categories yet')" />
                @endforelse
            </x-card>
        </div>
        @if ($canManage)
            <div class="col-xl-4">
                <x-card :title="__('Add a category')" icon="bi-plus-lg">
                    <form method="POST" action="{{ route('restaurant.menu.categories.store') }}" data-add-category>
                        @csrf
                        @foreach ($languages as $code => $language)
                            <x-form.input :name="'name['.$code.']'" :id="'new-category-'.$code" :label="__('Name').' ('.$language.')'" :required="$code === 'en'" />
                        @endforeach
                        <x-form.select name="parent_id" id="new-category-parent" :label="__('Inside')" :options="$options" :placeholder="__('Top level')" />
                        <x-form.select name="colour" id="new-category-colour" :label="__('Button colour')" :options="$colours" value="primary" :search="false" required />
                        <x-form.select name="revenue_class" id="new-category-class" :label="__('Sales count as')" :options="$revenueClasses" :placeholder="__('Same as parent (food)')" :search="false" />
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add category') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts::app>
