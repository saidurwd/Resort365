@php($title = $policy ? $policy->name : __('New deposit policy'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Policies') => route('rates.policies.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $policy ? route('rates.deposit-policies.update', $policy) : route('rates.deposit-policies.store') }}">
                @csrf
                @if ($policy)
                    @method('PUT')
                @endif
                <x-card :title="__('Deposit policy')" icon="bi-piggy-bank">
                    <div class="row">
                        <div class="col-md-7"><x-form.input name="name" :label="__('Name')" :value="$policy?->name" required /></div>
                        <div class="col-md-5"><x-form.select name="type" :label="__('Deposit')" :options="\Modules\Rates\Enums\DepositType::options()" :value="$policy?->type->value ?? 'percentage'" required :search="false" /></div>
                        <div class="col-md-4"><x-form.input name="default_percent" :label="__('Default %')" :value="$policy?->default_percent ?? '30'" required inputmode="decimal" :help="__('Suggested on new bookings.')" /></div>
                        <div class="col-md-4"><x-form.input name="min_percent" :label="__('Minimum %')" :value="$policy?->min_percent" inputmode="decimal" :help="__('Empty = no limit. Lower needs permission.')" /></div>
                        <div class="col-md-4"><x-form.input name="max_percent" :label="__('Maximum %')" :value="$policy?->max_percent" inputmode="decimal" :help="__('Empty = no limit.')" /></div>
                        <div class="col-md-6"><x-form.money name="fixed_amount" :label="__('Fixed amount')" :currency="$currency" :value="$policy?->fixed_amount" :help="__('For the fixed-amount type.')" /></div>
                    </div>
                </x-card>
                <x-card :title="__('Deadlines')" icon="bi-alarm">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="due_within_minutes" type="number" min="1" :label="__('Deposit due within (minutes)')" :value="$policy?->due_within_minutes ?? 30" required :help="__('30 = half an hour, 1440 = a day.')" /></div>
                        <div class="col-md-6"><x-form.input name="full_payment_within_hours" type="number" min="1" :label="__('Full payment if arriving within (hours)')" :value="$policy?->full_payment_within_hours" :help="__('Empty = never.')" /></div>
                        <div class="col-md-6"><x-form.select name="balance_due_rule" :label="__('Balance due')" :options="\Modules\Rates\Enums\BalanceDueRule::options()" :value="$policy?->balance_due_rule->value ?? 'at_check_in'" required :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="balance_due_days" type="number" min="1" :label="__('Days before arrival')" :value="$policy?->balance_due_days" /></div>
                    </div>
                    @foreach (['auto_cancel_unpaid' => [__('Cancel unpaid bookings automatically when the deposit is late'), $policy?->auto_cancel_unpaid ?? true], 'is_default' => [__('Default policy of this property'), $policy?->is_default ?? false]] as $field => [$label, $checked])
                        <div class="form-check form-switch">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $field }}" id="field-{{ $field }}" value="1" @checked(old($field, $checked))>
                            <label class="form-check-label" for="field-{{ $field }}">{{ $label }}</label>
                        </div>
                    @endforeach
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
