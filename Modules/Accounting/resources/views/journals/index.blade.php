<x-layouts::app :title="__('Journal entries')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Journal entries') => null]">
    <x-slot:actions>
        @if ($canCreate)
            <a href="{{ route('accounting.journals.create') }}" class="btn btn-primary" data-new-entry><i class="bi bi-plus-lg"></i> {{ __('New entry') }}</a>
        @endif
    </x-slot:actions>

    <form method="GET" action="{{ route('accounting.journals.index') }}" class="d-flex flex-wrap gap-2 align-items-end mb-3" data-journal-filter>
        <x-form.select name="status" :label="__('Status')" :options="$statuses" :value="request('status')" :placeholder="__('Any')" :search="false" wrapper-class="mb-0" />
        <x-form.input name="from" type="date" :label="__('From')" :value="request('from')" wrapper-class="mb-0" />
        <x-form.input name="to" type="date" :label="__('To')" :value="request('to')" wrapper-class="mb-0" />
        <button type="submit" class="btn btn-outline-primary">{{ __('Show') }}</button>
    </form>
    <x-card body-class="p-0">
        <x-datatable id="journal-entries" :url="route('accounting.journals.data', request()->only(['status', 'from', 'to']))" :columns="$columns" :order="[[1, 'desc']]" :empty-text="__('No journal entries yet.')" />
    </x-card>
</x-layouts::app>
