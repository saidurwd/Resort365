@php
    $editors = $groups->map(fn ($group) => ['id' => $group->id, 'name' => $group->name, 'min' => $group->min_select, 'max' => $group->max_select,
        'url' => route('restaurant.menu.modifiers.update', $group),
        'modifiers' => $group->modifiers->map(fn ($modifier) => ['id' => $modifier->id, 'name' => $modifier->name, 'price_delta' => $modifier->price_delta, 'is_active' => $modifier->is_active])->values()])->values();
@endphp
<x-layouts::app :title="__('Modifiers')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Modifiers') => null]">
    <div class="row">
        @foreach ($editors->push(['id' => null, 'name' => '', 'min' => 0, 'max' => 1, 'url' => route('restaurant.menu.modifiers.store'), 'modifiers' => [['id' => null, 'name' => '', 'price_delta' => '0', 'is_active' => true]]])->all() as $group)
            @continue(! $canManage && $group['id'] === null)
            <div class="col-xl-6">
                <x-card :title="$group['id'] ? $group['name'] : __('New modifier group')" :icon="$group['id'] ? 'bi-sliders' : 'bi-plus-lg'" :data-modifier-group="$group['name']">
                    <form method="POST" action="{{ $group['url'] }}" x-data="{ modifiers: @js($group['modifiers']) }">
                        @csrf
                        @if ($group['id']) @method('PUT') @endif
                        <fieldset @disabled(! $canManage)>
                            <div class="row">
                                <div class="col-md-6"><x-form.input name="name" :id="'group-name-'.($group['id'] ?? 'new')" :label="__('Name')" :value="$group['name']" required /></div>
                                <div class="col-3"><x-form.input name="min_select" type="number" min="0" :id="'group-min-'.($group['id'] ?? 'new')" :label="__('At least')" :value="$group['min']" required /></div>
                                <div class="col-3"><x-form.input name="max_select" type="number" min="1" :id="'group-max-'.($group['id'] ?? 'new')" :label="__('At most')" :value="$group['max']" required /></div>
                            </div>
                            <table class="table table-sm align-middle">
                                <thead><tr><th>{{ __('Option') }}</th><th>{{ __('Adds to price') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
                                <tbody>
                                    <template x-for="(modifier, index) in modifiers" :key="index">
                                        <tr>
                                            <td>
                                                <input type="hidden" :name="'modifiers[' + index + '][id]'" :value="modifier.id ?? ''" :disabled="! modifier.id">
                                                <input type="text" class="form-control form-control-sm" :name="'modifiers[' + index + '][name]'" x-model="modifier.name" maxlength="100" required aria-label="{{ __('Option') }}">
                                            </td>
                                            <td><input type="number" step="0.01" class="form-control form-control-sm" :name="'modifiers[' + index + '][price_delta]'" x-model="modifier.price_delta" aria-label="{{ __('Adds to price') }}"></td>
                                            <td>
                                                <input type="hidden" :name="'modifiers[' + index + '][is_active]'" value="0">
                                                <input type="checkbox" class="form-check-input" :name="'modifiers[' + index + '][is_active]'" value="1" :checked="modifier.is_active" aria-label="{{ __('Offered') }}">
                                            </td>
                                            <td><button type="button" class="btn btn-sm btn-outline-danger" @click="modifiers.splice(index, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="modifiers.push({ id: null, name: '', price_delta: '0', is_active: true })"><i class="bi bi-plus"></i> {{ __('Add option') }}</button>
                            @if ($canManage)<button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save"></i> {{ __('Save group') }}</button>@endif
                        </fieldset>
                    </form>
                </x-card>
            </div>
        @endforeach
    </div>
    <p class="small text-body-secondary">{{ __('"At least 1" makes the choice required (e.g. cooking level). Attach groups to items on the item page.') }}</p>
</x-layouts::app>
