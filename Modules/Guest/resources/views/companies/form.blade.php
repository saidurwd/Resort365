@php($title = $record ? $record->name : __('New company'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Companies') => route('guest.companies.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $record ? route('guest.companies.update', $record) : route('guest.companies.store') }}">
                @csrf
                @if ($record)
                    @method('PUT')
                @endif
                <x-card :title="__('Company')" icon="bi-building">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                        <div class="col-md-6"><x-form.input name="legal_name" :label="__('Legal name')" :value="$record?->legal_name" /></div>
                        <div class="col-md-4"><x-form.input name="tax_number" :label="__('Tax number (BIN)')" :value="$record?->tax_number" /></div>
                        <div class="col-md-4"><x-form.money name="credit_limit" :label="__('Credit limit')" :currency="$currency" :value="$record?->credit_limit ?? '0.00'" required :help="__('0 means no credit.')" /></div>
                        <div class="col-md-4"><x-form.input name="payment_terms_days" type="number" min="0" :label="__('Payment terms (days)')" :value="$record?->payment_terms_days ?? 0" required /></div>
                    </div>
                    @include('guest::partials.contact-fields')
                    @include('guest::partials.active-and-notes')
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('guest.companies.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save company') }}</button>
                </div>
            </form>
        </div>
        @if ($record)
            <div class="col-xl-4"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
