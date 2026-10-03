@php
    $title = $policy ? $policy->name : __('New cancellation policy');
    $rules = old('rules', $policy?->rules->sortByDesc('days_before_from')->map(fn ($r) => [
        'days_before_from' => $r->days_before_from, 'days_before_to' => $r->days_before_to, 'charge_type' => $r->charge_type->value, 'charge_value' => $r->charge_value,
    ])->values()->all() ?? [['days_before_from' => '', 'days_before_to' => '', 'charge_type' => 'percent_of_deposit', 'charge_value' => '']]);
@endphp

<x-layouts::app :title="$title" :breadcrumbs="[__('Policies') => route('rates.policies.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $policy ? route('rates.cancellation-policies.update', $policy) : route('rates.cancellation-policies.store') }}">
                @csrf
                @if ($policy)
                    @method('PUT')
                @endif
                <x-card :title="__('Cancellation policy')" icon="bi-x-octagon">
                    <x-form.input name="name" :label="__('Name')" :value="$policy?->name" required />
                    <x-form.field name="description" :label="__('Description for guests')">
                        <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $policy?->description) }}</textarea>
                    </x-form.field>
                </x-card>
                <x-card :title="__('Charges by days before arrival')" icon="bi-calendar-x">
                    <div x-data="{ rules: @js(array_values($rules)) }" data-cancellation-rules>
                        <div class="row g-2 small text-body-secondary mb-1 d-none d-md-flex">
                            <div class="col-md-2">{{ __('From day') }}</div><div class="col-md-2">{{ __('To day') }}</div><div class="col-md-4">{{ __('Charge') }}</div><div class="col-md-3">{{ __('Value') }}</div>
                        </div>
                        <template x-for="(rule, index) in rules" :key="index">
                            <div class="row g-2 mb-2 align-items-center">
                                <div class="col-md-2"><input type="number" min="0" class="form-control" :name="`rules[${index}][days_before_from]`" x-model="rule.days_before_from" aria-label="{{ __('From day') }}" required></div>
                                <div class="col-md-2"><input type="number" min="0" class="form-control" :name="`rules[${index}][days_before_to]`" x-model="rule.days_before_to" aria-label="{{ __('To day') }}" placeholder="{{ __('or more') }}"></div>
                                <div class="col-md-4">
                                    <select class="form-select" :name="`rules[${index}][charge_type]`" x-model="rule.charge_type" aria-label="{{ __('Charge') }}">
                                        @foreach (\Modules\Rates\Enums\CancellationChargeType::options() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3"><input type="text" inputmode="decimal" class="form-control" :name="`rules[${index}][charge_value]`" x-model="rule.charge_value" aria-label="{{ __('Value') }}" required></div>
                                <div class="col-md-1"><button type="button" class="btn btn-outline-danger" @click="rules.splice(index, 1)" :disabled="rules.length === 1" aria-label="{{ __('Remove tier') }}"><i class="bi bi-x-lg"></i></button></div>
                            </div>
                        </template>
                        <button type="button" class="btn btn-sm btn-outline-primary" @click="rules.push({ days_before_from: '', days_before_to: '', charge_type: 'percent_of_deposit', charge_value: '' })"><i class="bi bi-plus-lg"></i> {{ __('Add tier') }}</button>
                    </div>
                    @foreach ($errors->get('rules*') as $messages)
                        @foreach ((array) $messages as $message)
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @endforeach
                    @endforeach
                    <p class="small text-body-secondary mt-2 mb-0">{{ __('Example: 15 or more days: 0% of the deposit; 7–14 days: 50% of the deposit; 0–6 days: 100% of the deposit.') }}</p>
                </x-card>
                <x-card :title="__('No-show')" icon="bi-person-x">
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="no_show_charge_type" :label="__('Charge')" :options="\Modules\Rates\Enums\CancellationChargeType::options()" :value="$policy?->no_show_charge_type?->value" :placeholder="__('As on the arrival day')" :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="no_show_charge_value" :label="__('Value')" :value="$policy?->no_show_charge_value" inputmode="decimal" /></div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_default" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_default" id="field-is_default" value="1" @checked(old('is_default', $policy?->is_default ?? false))>
                        <label class="form-check-label" for="field-is_default">{{ __('Default policy of this property') }}</label>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('rates.policies.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save policy') }}</button>
                </div>
            </form>
        </div>
        @if ($policy)
            <div class="col-xl-4"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
