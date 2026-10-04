@php($title = $service ? $service->name : __('New extra'))

<x-layouts::app :title="$title" :subtitle="app(\App\Support\Tenancy\PropertyContext::class)->currentName()" :breadcrumbs="[__('Extras') => route('billing.extra-services.index'), $title => null]">
    <form method="POST" action="{{ $service ? route('billing.extra-services.update', $service) : route('billing.extra-services.store') }}" data-extra-form>
        @csrf
        @if ($service) @method('PUT') @endif
        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Extra')" icon="bi-bag-plus">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$service?->name" required maxlength="100" /></div>
                        <div class="col-md-4"><x-form.input name="unit" :label="__('Per')" :value="$service?->unit" :help="__('e.g. trip, night, piece')" maxlength="30" /></div>
                        <div class="col-md-6"><x-form.select name="charge_code_id" :label="__('Charge code')" :options="$codes" :value="$service?->charge_code_id" required :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="unit_price" type="number" step="0.01" min="0" :label="__('Price')" :value="$service?->unit_price" required /></div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$service?->sort_order ?? 0" /></div>
                    </div>
                    @include('billing::partials.switch', ['name' => 'price_includes_tax', 'label' => __('The price includes tax'), 'checked' => $service?->price_includes_tax ?? false])
                    @include('billing::partials.switch', ['name' => 'is_active', 'label' => __('Active'), 'checked' => $service?->is_active ?? true])
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('billing.extra-services.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save extra') }}</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts::app>
