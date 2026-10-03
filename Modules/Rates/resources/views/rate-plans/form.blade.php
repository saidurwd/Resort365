@php
    $title = $plan ? $plan->name : __('New rate plan');
    $channels = old('channels', $plan?->channels ?? array_column(\Modules\Rates\Enums\BookingChannel::cases(), 'value'));
@endphp

<x-layouts::app :title="$title" :breadcrumbs="[__('Rate plans') => route('rates.rate-plans.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $plan ? route('rates.rate-plans.update', $plan) : route('rates.rate-plans.store') }}">
                @csrf
                @if ($plan)
                    @method('PUT')
                @endif
                <x-card :title="__('Rate plan')" icon="bi-tags">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$plan?->code" required :help="__('E.g. BB.')" /></div>
                        <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$plan?->name" required /></div>
                        <div class="col-md-3"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$plan?->sort_order ?? 0" /></div>
                        <div class="col-12">
                            <x-form.field name="description" :label="__('Description')">
                                <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $plan?->description) }}</textarea>
                            </x-form.field>
                        </div>
                    </div>
                </x-card>

                <x-card :title="__('Meals')" icon="bi-cup-hot">
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="meal_plan" :label="__('Meal plan')" :options="\Modules\Rates\Enums\MealPlan::options()" :value="$plan?->meal_plan->value ?? 'EP'" required :search="false" /></div>
                        <div class="col-md-3"><x-form.money name="meal_adult_amount" :label="__('Meal value / adult / night')" :currency="$currency" :value="$plan?->meal_adult_amount ?? '0.00'" /></div>
                        <div class="col-md-3"><x-form.money name="meal_child_amount" :label="__('Meal value / child / night')" :currency="$currency" :value="$plan?->meal_child_amount ?? '0.00'" /></div>
                    </div>
                    <p class="small text-body-secondary mb-0">{{ __('The part of the rate that pays for included meals: booked as food revenue and used when a guest eats at an outlet.') }}</p>
                </x-card>

                <x-card :title="__('Taxes and selling')" icon="bi-receipt">
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="tax_category_id" :label="__('Tax category')" :options="$taxCategories" :value="$plan?->tax_category_id" :placeholder="__('No taxes')" :search="false" /></div>
                        <div class="col-md-3"><x-form.date name="valid_from" :label="__('Valid from')" :value="$plan?->valid_from?->toDateString()" /></div>
                        <div class="col-md-3"><x-form.date name="valid_to" :label="__('Valid to')" :value="$plan?->valid_to?->toDateString()" /></div>
                    </div>
                    @foreach ([
                        ['prices_include_tax', __('Prices include tax (otherwise taxes are added on top)'), $plan?->prices_include_tax ?? false],
                        ['is_refundable', __('Refundable'), $plan?->is_refundable ?? true],
                        ['is_active', __('Active'), $plan?->is_active ?? true],
                    ] as [$field, $label, $checked])
                        <div class="form-check form-switch">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $field }}" id="field-{{ $field }}" value="1" @checked(old($field, $checked))>
                            <label class="form-check-label" for="field-{{ $field }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <div class="mt-3">
                        <label class="form-label">{{ __('Sold through') }}<span class="required-marker" aria-hidden="true">*</span></label>
                        @foreach (\Modules\Rates\Enums\BookingChannel::cases() as $channel)
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $channel->value }}" id="channel-{{ $channel->value }}" @checked(in_array($channel->value, $channels, true))>
                                <label class="form-check-label" for="channel-{{ $channel->value }}">{{ $channel->label() }}</label>
                            </div>
                        @endforeach
                        @error('channels')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6"><x-form.select name="deposit_policy_id" :label="__('Deposit policy')" :options="$depositPolicies" :value="$plan?->deposit_policy_id" :placeholder="__('Property default')" :search="false" /></div>
                        <div class="col-md-6"><x-form.select name="cancellation_policy_id" :label="__('Cancellation policy')" :options="$cancellationPolicies" :value="$plan?->cancellation_policy_id" :placeholder="__('Property default')" :search="false" /></div>
                    </div>
                </x-card>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('rates.rate-plans.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save rate plan') }}</button>
                </div>
            </form>
        </div>
        @if ($plan)
            <div class="col-xl-4"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
