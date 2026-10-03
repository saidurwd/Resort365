<x-layouts::app :title="__('Guests')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Guests') => null]">
    @can('create', \Modules\Guest\Models\Guest::class)
        <x-slot:actions>
            <a href="{{ route('guest.guests.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> {{ __('New guest') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <ul class="nav nav-pills mb-3" data-guest-filters>
            @foreach (['all' => __('All guests'), 'vip' => __('VIP'), 'blacklisted' => __('Blacklisted')] as $key => $label)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $filter === $key]) href="{{ route('guest.guests.index', array_filter(['filter' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>
        <p class="small text-body-secondary">{{ __('Search by name, phone, email or ID number. Names match from the start of each word, e.g. "rah udd".') }}</p>
        <x-datatable id="guests-table" :url="route('guest.guests.data', $filter === 'all' ? [] : ['filter' => $filter])" :columns="$columns" :order="[[0, 'asc']]" :search="$search" :empty-text="__('No guests found.')" />
    </x-card>
</x-layouts::app>
