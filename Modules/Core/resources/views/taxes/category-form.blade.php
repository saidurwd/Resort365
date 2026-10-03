@php($title = $category ? $category->name : __('New tax category'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Taxes') => route('core.taxes.index'), $title => null]">
    <div class="row">
        <div class="col-xl-7">
            <form method="POST" action="{{ $category ? route('core.tax-categories.update', $category) : route('core.tax-categories.store') }}">
                @csrf
                @if ($category)
                    @method('PUT')
                @endif
                <x-card :title="__('Tax category')" icon="bi-collection">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$category?->code" required :help="__('E.g. ROOM, FNB.')" /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$category?->name" required /></div>
                    </div>
                    <label class="form-label">{{ __('Taxes') }}</label>
                    <p class="small text-body-secondary">{{ __('Applied in their calculation order. No taxes means exempt.') }}</p>
                    @foreach ($taxes as $tax)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tax_ids[]" value="{{ $tax->id }}" id="tax-{{ $tax->id }}" @checked(in_array($tax->id, old('tax_ids', $selected)))>
                            <label class="form-check-label" for="tax-{{ $tax->id }}">
                                <span class="text-body-secondary">{{ $tax->sort_order }}.</span> {{ $tax->name }} {{ $tax->rateLabel() }}
                                @if ($tax->is_compound)<span class="badge text-bg-info">{{ __('compound') }}</span>@endif
                                @unless ($tax->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless
                            </label>
                        </div>
                    @endforeach
                    <div class="form-check form-switch mt-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $category?->is_active ?? true))>
                        <label class="form-check-label" for="field-is_active">{{ __('Active (can be chosen for new rate plans, menu items and charges)') }}</label>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('core.taxes.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save category') }}</button>
                </div>
            </form>
        </div>
        @if ($category)
            <div class="col-xl-5"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
