@php
    $dayNames = ['mon' => __('Monday'), 'tue' => __('Tuesday'), 'wed' => __('Wednesday'), 'thu' => __('Thursday'), 'fri' => __('Friday'), 'sat' => __('Saturday'), 'sun' => __('Sunday')];
    $hours = old('opening_hours', $outlet?->opening_hours ?? []);
@endphp
<x-layouts::app :title="$outlet ? __('Edit :name', ['name' => $outlet->name]) : __('New outlet')"
    :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Outlets') => route('restaurant.outlets.index'), ($outlet?->name ?? __('New')) => null]">
    <form method="POST" action="{{ $outlet ? route('restaurant.outlets.update', $outlet) : route('restaurant.outlets.store') }}" data-outlet-form>
        @csrf
        @if ($outlet) @method('PUT') @endif
        <div class="row">
            <div class="col-xl-7">
                <x-card :title="__('Outlet')" icon="bi-cup-hot">
                    <div class="row">
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="old('name', $outlet?->name)" required /></div>
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="old('code', $outlet?->code)" required /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="type" :label="__('Type')" :options="$types" :value="old('type', $outlet?->type->value)" :search="false" required /></div>
                        <div class="col-md-6"><x-form.input name="bill_prefix" :label="__('Bill number prefix')" :value="old('bill_prefix', $outlet?->bill_prefix)" required :help="__('E.g. MR for MR-2026-00001.')" /></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><x-form.select name="default_tax_category_id" :label="__('Tax category')" :options="$taxCategories" :value="old('default_tax_category_id', $outlet?->default_tax_category_id)" :placeholder="__('No tax')" :help="__('Service charge and VAT, from Setup → Taxes. Menu items may choose another.')" /></div>
                        <div class="col-md-6 pt-md-4">
                            <div class="form-check mt-2">
                                <input type="hidden" name="prices_include_tax" value="0">
                                <input type="checkbox" class="form-check-input" id="field-prices_include_tax" name="prices_include_tax" value="1" @checked(old('prices_include_tax', $outlet?->prices_include_tax))>
                                <label class="form-check-label" for="field-prices_include_tax">{{ __('Menu prices include tax') }}</label>
                            </div>
                            <div class="form-check">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" class="form-check-input" id="field-is_active" name="is_active" value="1" @checked(old('is_active', $outlet?->is_active ?? true))>
                                <label class="form-check-label" for="field-is_active">{{ __('Active') }}</label>
                            </div>
                        </div>
                    </div>
                    <x-form.input name="receipt_header" :label="__('Receipt header')" :value="old('receipt_header', $outlet?->receipt_header)" />
                    <x-form.input name="receipt_footer" :label="__('Receipt footer')" :value="old('receipt_footer', $outlet?->receipt_footer)" />
                    <x-form.input name="sort_order" type="number" min="0" :label="__('Order in lists')" :value="old('sort_order', $outlet?->sort_order ?? 0)" />
                </x-card>
            </div>
            <div class="col-xl-5">
                <x-card :title="__('Opening hours')" icon="bi-clock">
                    <table class="table table-sm align-middle mb-0" data-opening-hours>
                        <thead><tr><th>{{ __('Day') }}</th><th>{{ __('Opens') }}</th><th>{{ __('Closes') }}</th></tr></thead>
                        <tbody>
                            @foreach ($weekdays as $day)
                                <tr>
                                    <td>{{ $dayNames[$day] }}</td>
                                    <td><input type="time" name="opening_hours[{{ $day }}][open]" value="{{ $hours[$day]['open'] ?? '' }}" class="form-control form-control-sm" aria-label="{{ __(':day opens', ['day' => $dayNames[$day]]) }}"></td>
                                    <td><input type="time" name="opening_hours[{{ $day }}][close]" value="{{ $hours[$day]['close'] ?? '' }}" @class(['form-control form-control-sm', 'is-invalid' => $errors->has('opening_hours.'.$day.'.close')]) aria-label="{{ __(':day closes', ['day' => $dayNames[$day]]) }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="small text-body-secondary mt-2 mb-0">{{ __('Leave a day empty when the outlet is closed. A closing time before the opening time runs past midnight.') }}</p>
                </x-card>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save outlet') }}</button>
    </form>
</x-layouts::app>
