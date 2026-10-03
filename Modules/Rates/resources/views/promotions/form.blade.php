@php($title = $promotion ? $promotion->name : __('New promotion'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Promotions') => route('rates.promotions.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $promotion ? route('rates.promotions.update', $promotion) : route('rates.promotions.store') }}">
                @csrf
                @if ($promotion)
                    @method('PUT')
                @endif
                <x-card :title="__('Promotion')" icon="bi-gift">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$promotion?->name" required /></div>
                        <div class="col-md-4"><x-form.input name="code" :label="__('Promo code')" :value="$promotion?->code" :help="__('Empty = applies automatically.')" /></div>
                        <div class="col-md-6"><x-form.select name="discount_type" :label="__('Discount')" :options="\Modules\Rates\Enums\DiscountType::options()" :value="$promotion?->discount_type->value ?? 'percent'" required :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="discount_value" :label="__('Value (% or :currency)', ['currency' => $currency])" :value="$promotion?->discount_value" required inputmode="decimal" /></div>
                        <div class="col-12">
                            <x-form.field name="description" :label="__('Description')">
                                <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $promotion?->description) }}</textarea>
                            </x-form.field>
                        </div>
                    </div>
                </x-card>
                <x-card :title="__('Conditions')" icon="bi-funnel">
                    <p class="small text-body-secondary">{{ __('Leave a condition empty to allow anything.') }}</p>
                    <div class="row">
                        <div class="col-md-3"><x-form.date name="stay_from" :label="__('Stays from')" :value="$promotion?->stay_from?->toDateString()" /></div>
                        <div class="col-md-3"><x-form.date name="stay_to" :label="__('Stays to')" :value="$promotion?->stay_to?->toDateString()" /></div>
                        <div class="col-md-3"><x-form.date name="book_from" :label="__('Booked from')" :value="$promotion?->book_from?->toDateString()" /></div>
                        <div class="col-md-3"><x-form.date name="book_to" :label="__('Booked to')" :value="$promotion?->book_to?->toDateString()" /></div>
                        <div class="col-md-4"><x-form.input name="min_nights" type="number" min="1" :label="__('Minimum nights')" :value="$promotion?->min_nights" /></div>
                        <div class="col-md-4"><x-form.input name="max_nights" type="number" min="1" :label="__('Maximum nights')" :value="$promotion?->max_nights" /></div>
                        <div class="col-md-4"><x-form.input name="min_advance_days" type="number" min="0" :label="__('Booked at least (days ahead)')" :value="$promotion?->min_advance_days" /></div>
                        <div class="col-md-6"><x-form.select name="rate_plan_ids" :label="__('Rate plans')" :options="$plans" :value="$promotion?->rate_plan_ids ?? []" multiple :help="__('Empty = all rate plans.')" /></div>
                        <div class="col-md-6"><x-form.select name="unit_keys" :label="__('Room / cottage types')" :options="$units" :value="$promotion?->unit_keys ?? []" multiple :help="__('Empty = all types.')" /></div>
                        <div class="col-md-4"><x-form.input name="usage_limit" type="number" min="1" :label="__('Usage limit')" :value="$promotion?->usage_limit" :help="__('Bookings in total.')" /></div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="field-is_active" value="1" @checked(old('is_active', $promotion?->is_active ?? true))>
                        <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('rates.promotions.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save promotion') }}</button>
                </div>
            </form>
        </div>
        @if ($promotion)
            <div class="col-xl-4"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
