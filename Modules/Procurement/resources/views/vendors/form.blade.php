@php($editing = $vendor !== null)
<x-layouts::app :title="$editing ? __('Change vendor') : __('Add vendor')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendors') => route('procurement.vendors.index'), $editing ? $vendor->name : __('New') => null]">
    <form method="POST" action="{{ $editing ? route('procurement.vendors.update', $vendor) : route('procurement.vendors.store') }}" data-vendor-form>
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-card :title="__('Profile')" icon="bi-shop">
            <div class="row">
                <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="old('code', $vendor?->code)" :help="__('Leave blank to number it automatically.')" /></div>
                <div class="col-md-5"><x-form.input name="name" :label="__('Name')" :value="old('name', $vendor?->name)" required /></div>
                <div class="col-md-4"><x-form.input name="tax_id" :label="__('Tax ID')" :value="old('tax_id', $vendor?->tax_id)" /></div>
                <div class="col-md-4"><x-form.input name="email" type="email" :label="__('Email')" :value="old('email', $vendor?->email)" /></div>
                <div class="col-md-4"><x-form.input name="phone" :label="__('Phone')" :value="old('phone', $vendor?->phone)" /></div>
                <div class="col-md-4"><x-form.input name="address" :label="__('Address')" :value="old('address', $vendor?->address)" /></div>
                <div class="col-md-3"><x-form.input name="currency" :label="__('Currency')" :value="old('currency', $vendor?->currency ?? 'BDT')" maxlength="3" required /></div>
                <div class="col-md-3"><x-form.input name="payment_terms_days" type="number" :label="__('Payment terms (days)')" :value="old('payment_terms_days', $vendor?->payment_terms_days ?? 30)" required /></div>
                <div class="col-md-3"><x-form.select name="rating" :label="__('Rating')" :options="[1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★']" :value="old('rating', $vendor?->rating)" :placeholder="__('Not rated')" /></div>
                <div class="col-md-3"><x-form.select name="status" :label="__('Status')" :options="collect(\Modules\Procurement\Enums\VendorStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all()" :value="old('status', $vendor?->status?->value ?? 'active')" required /></div>
            </div>
        </x-card>
        <x-card :title="__('Bank details')" icon="bi-bank">
            <div class="row">
                <div class="col-md-3"><x-form.input name="bank_name" :label="__('Bank')" :value="old('bank_name', $vendor?->bank_name)" /></div>
                <div class="col-md-3"><x-form.input name="bank_branch" :label="__('Branch')" :value="old('bank_branch', $vendor?->bank_branch)" /></div>
                <div class="col-md-3"><x-form.input name="bank_account_name" :label="__('Account name')" :value="old('bank_account_name', $vendor?->bank_account_name)" /></div>
                <div class="col-md-3"><x-form.input name="bank_account_no" :label="__('Account number')" :value="old('bank_account_no', $vendor?->bank_account_no)" /></div>
                <div class="col-12"><x-form.input name="notes" :label="__('Notes')" :value="old('notes', $vendor?->notes)" /></div>
            </div>
        </x-card>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">{{ __('Save') }}</button><a href="{{ $editing ? route('procurement.vendors.show', $vendor) : route('procurement.vendors.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a></div>
    </form>
</x-layouts::app>
