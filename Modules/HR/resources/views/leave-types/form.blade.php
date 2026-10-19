@php($title = $record ? $record->name : __('New leave type'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Setup') => null, __('Leave types') => route('hr.leave-types.index'), $title => null]">
    <form method="POST" action="{{ $record ? route('hr.leave-types.update', $record) : route('hr.leave-types.store') }}" data-leave-type-form>
        @csrf
        @if ($record)
            @method('PUT')
        @endif
        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Leave type')" icon="bi-calendar2-heart">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                        <div class="col-md-6"><x-form.select name="gender" :label="__('For')" :options="$genders" :value="$record?->gender?->value" :placeholder="__('Everyone')" :search="false" /></div>
                        <div class="col-md-6"><x-form.select name="color" :label="__('Colour')" :options="['primary' => __('Blue'), 'success' => __('Green'), 'warning' => __('Yellow'), 'danger' => __('Red'), 'info' => __('Light blue'), 'secondary' => __('Grey')]" :value="$record?->color ?? 'primary'" :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="document_after_days" type="number" min="1" :label="__('Paper expected after (days)')" :value="$record?->document_after_days" :help="__('e.g. a medical certificate for sick leave beyond 3 days.')" /></div>
                    </div>
                    @foreach (['is_paid' => [__('Paid leave'), true], 'has_balance' => [__('Has a yearly balance'), true], 'counts_non_working_days' => [__('Counts weekends, days off and holidays'), false], 'is_active' => [__('Active'), true]] as $field => [$label, $default])
                        <div class="form-check form-switch mb-1">
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="{{ $field }}" id="field-{{ $field }}" value="1" @checked(old($field, $record?->{$field} ?? $default))>
                            <label class="form-check-label" for="field-{{ $field }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </x-card>
            </div>
            <div class="col-xl-6">
                <x-card :title="__('Policy')" icon="bi-clipboard-check">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="annual_days" type="number" step="0.5" min="0" :label="__('Days a year')" :value="$policy ? rtrim(rtrim((string) $policy->annual_days, '0'), '.') : '0'" /></div>
                        <div class="col-md-6"><x-form.select name="accrual" :label="__('Earned')" :options="$accruals" :value="$policy?->accrual->value ?? 'yearly'" :search="false" /></div>
                        <div class="col-md-6"><x-form.input name="carry_forward_max" type="number" step="0.5" min="0" :label="__('Carry forward at most (days)')" :value="$policy ? rtrim(rtrim((string) $policy->carry_forward_max, '0'), '.') : '0'" /></div>
                        <div class="col-md-6"><x-form.input name="min_service_months" type="number" min="0" :label="__('Months of service needed')" :value="$policy?->min_service_months ?? 0" /></div>
                        <div class="col-md-6"><x-form.input name="max_consecutive_days" type="number" min="1" :label="__('Longest single request (days)')" :value="$policy?->max_consecutive_days" /></div>
                        <div class="col-md-6"><x-form.select name="employment_types" :label="__('Applies to')" :options="$employmentTypes" :value="$policy?->employment_types ?? []" multiple :placeholder="__('All employment types')" /></div>
                    </div>
                    <p class="small text-body-secondary mb-0">{{ __('Changing the policy affects balances granted from now on, not the ones already granted. Balances are granted and, for monthly accrual, topped up every night.') }}</p>
                </x-card>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('hr.leave-types.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</x-layouts::app>
