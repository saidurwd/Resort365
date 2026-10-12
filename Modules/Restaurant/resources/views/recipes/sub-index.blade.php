<x-layouts::app :title="__('Sub-recipes')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Sub-recipes') => null]">
    <x-slot:actions>
        @if ($canManage)<a href="{{ route('restaurant.sub-recipes.create') }}" class="btn btn-primary" data-new-sub-recipe><i class="bi bi-plus-lg"></i> {{ __('New sub-recipe') }}</a>@endif
    </x-slot:actions>
    <x-card body-class="p-0">
        <table class="table table-sm table-hover align-middle mb-0" data-sub-recipes>
            <thead><tr><th class="ps-3">{{ __('Sub-recipe') }}</th><th class="text-end">{{ __('Yield') }}</th><th class="text-end">{{ __('Ingredients') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($recipes as $recipe)
                    <tr data-sub-recipe="{{ $recipe->name }}"><td class="ps-3">{{ $recipe->name }}</td><td class="text-end font-monospace">{{ rtrim(rtrim((string) $recipe->yield_quantity, '0'), '.') }} {{ $recipe->yield_unit }}</td><td class="text-end">{{ $recipe->lines_count }}</td>
                        <td class="text-end pe-3"><a href="{{ route('restaurant.sub-recipes.edit', $recipe) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td></tr>
                @empty
                    <tr><td colspan="4"><x-empty-state :title="__('No sub-recipes yet')" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
