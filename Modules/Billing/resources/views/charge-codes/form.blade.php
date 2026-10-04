@php($title = $code ? $code->code : __('New charge code'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Charge codes') => route('billing.charge-codes.index'), $title => null]">
    <form method="POST" action="{{ $code ? route('billing.charge-codes.update', $code) : route('billing.charge-codes.store') }}" data-charge-code-form>
        @csrf
        @if ($code) @method('PUT') @endif
        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Charge code')" icon="bi-upc">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$code?->code" required maxlength="20" /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$code?->name" required maxlength="100" /></div>
                        <div class="col-md-6"><x-form.select name="category" :label="__('Category')" :options="\Modules\Billing\Enums\ChargeCategory::options()" :value="$code?->category->value" required :search="false" /></div>
                        <div class="col-md-6"><x-form.select name="tax_category_id" :label="__('Tax category')" :options="$taxCategories" :value="$code?->tax_category_id" :placeholder="__('No tax')" :search="false" /></div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$code?->sort_order ?? 0" /></div>
                    </div>
                    @include('billing::partials.switch', ['name' => 'is_active', 'label' => __('Active'), 'checked' => $code?->is_active ?? true])
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('billing.charge-codes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save charge code') }}</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts::app>
