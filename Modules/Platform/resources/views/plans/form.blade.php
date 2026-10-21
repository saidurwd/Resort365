@php($editing = $plan->exists)
<x-layouts::platform :title="$editing ? $plan->name : __('New plan')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Plans') => route('platform.plans.index'), $editing ? $plan->name : __('New') => null]">
    <form method="POST" action="{{ $editing ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" data-plan-form x-data="{ chosen: @js($plan->modules ?? []), needs: @js(collect($catalog)->map(fn ($m) => $m[1])->all()),
        toggle(module) { if (this.chosen.includes(module)) { this.chosen = this.chosen.filter((m) => m !== module && !(this.needs[m] || []).includes(module)); } else { this.add(module); } },
        add(module) { if (!this.chosen.includes(module)) { this.chosen.push(module); (this.needs[module] || []).forEach((m) => this.add(m)); } } }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="row">
            <div class="col-lg-6">
                <x-card :title="__('The plan')" icon="bi-box-seam">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="old('name', $plan->name)" required /></div>
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="old('code', $plan->code)" required :help="__('Lowercase letters, digits and hyphens.')" /></div>
                    </div>
                    <x-form.input name="description" :label="__('Description')" :value="old('description', $plan->description)" />
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="monthly_price" type="number" step="0.01" min="0" :label="__('Price a month')" :value="old('monthly_price', $plan->monthly_price)" required /></div>
                        <div class="col-md-4"><x-form.input name="yearly_price" type="number" step="0.01" min="0" :label="__('Price a year')" :value="old('yearly_price', $plan->yearly_price)" required /></div>
                        <div class="col-md-4"><x-form.input name="currency_code" :label="__('Currency')" :value="old('currency_code', $plan->currency_code)" required maxlength="3" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="max_properties" type="number" min="1" :label="__('Properties')" :value="old('max_properties', $plan->max_properties)" :help="__('Empty: no limit.')" /></div>
                        <div class="col-md-3"><x-form.input name="max_rooms" type="number" min="1" :label="__('Rooms')" :value="old('max_rooms', $plan->max_rooms)" /></div>
                        <div class="col-md-3"><x-form.input name="max_users" type="number" min="1" :label="__('Users')" :value="old('max_users', $plan->max_users)" /></div>
                        <div class="col-md-3"><x-form.input name="storage_gb" type="number" min="1" :label="__('Storage (GB)')" :value="old('storage_gb', $plan->storage_gb)" /></div>
                    </div>
                    <div class="form-check form-switch mb-2"><input type="hidden" name="api_access" value="0"><input class="form-check-input" type="checkbox" role="switch" id="api_access" name="api_access" value="1" @checked(old('api_access', $plan->api_access))><label class="form-check-label" for="api_access">{{ __('API access') }}</label></div>
                    <div class="form-check form-switch mb-2"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $plan->is_active))><label class="form-check-label" for="is_active">{{ __('Active (tenants can be moved to it)') }}</label></div>
                    <x-form.input name="sort_order" type="number" min="0" :label="__('Order')" :value="old('sort_order', $plan->sort_order)" />
                </x-card>
            </div>
            <div class="col-lg-6">
                <x-card :title="__('Modules')" icon="bi-grid" data-modules>
                    <p class="small text-body-secondary">{{ __('Choosing a module also chooses what it needs.') }}</p>
                    @foreach ($catalog as $alias => [$label, $needs])
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $alias }}" id="module-{{ $alias }}" :checked="chosen.includes('{{ $alias }}')" @click.prevent="toggle('{{ $alias }}')" data-module="{{ $alias }}">
                            <label class="form-check-label" for="module-{{ $alias }}">{{ __($label) }} @if ($needs !== [])<span class="small text-body-secondary">({{ __('needs') }} {{ implode(', ', $needs) }})</span>@endif</label>
                        </div>
                    @endforeach
                </x-card>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" data-save-plan>{{ __('Save the plan') }}</button>
    </form>
</x-layouts::platform>
