@php($title = $record ? $record->name : __('New travel agent'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Travel agents') => route('guest.travel-agents.index'), $title => null]">
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ $record ? route('guest.travel-agents.update', $record) : route('guest.travel-agents.store') }}">
                @csrf
                @if ($record)
                    @method('PUT')
                @endif
                <x-card :title="__('Travel agent')" icon="bi-globe2">
                    <div class="row">
                        <div class="col-md-3"><x-form.input name="code" :label="__('Code')" :value="$record?->code" required /></div>
                        <div class="col-md-9"><x-form.input name="name" :label="__('Name')" :value="$record?->name" required /></div>
                        <div class="col-md-6"><x-form.input name="commission_percent" :label="__('Commission %')" :value="$record?->commission_percent ?? '10.00'" required inputmode="decimal" /></div>
                        <div class="col-md-6"><x-form.money name="credit_limit" :label="__('Credit limit')" :currency="$currency" :value="$record?->credit_limit ?? '0.00'" required /></div>
                    </div>
                    @include('guest::partials.contact-fields')
                    @include('guest::partials.active-and-notes')
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('guest.travel-agents.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save travel agent') }}</button>
                </div>
            </form>
        </div>
        @if ($record)
            <div class="col-xl-4"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
