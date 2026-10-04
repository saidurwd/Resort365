<x-layouts::app :title="__('Quotes')" :subtitle="app(\App\Support\Tenancy\PropertyContext::class)->currentName()" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Quotes') => null]">
    @can('create', \Modules\Reservation\Models\Quote::class)
        <x-slot:actions>
            <a href="{{ route('reservation.bookings.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New quote') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <ul class="nav nav-pills mb-3" data-quote-filters>
            <li class="nav-item"><a @class(['nav-link', 'active' => $status === null]) href="{{ route('reservation.quotes.index') }}">{{ __('All') }}</a></li>
            @foreach ($statuses as $option)
                <li class="nav-item"><a @class(['nav-link', 'active' => $status === $option]) href="{{ route('reservation.quotes.index', ['status' => $option->value]) }}">{{ $option->label() }}</a></li>
            @endforeach
        </ul>
        <p class="small text-body-secondary">{{ __('Make a quote in the booking wizard: price the stay, then choose "Save as quote instead". Search by quote number or guest.') }}</p>
        <x-datatable id="quotes-table" :url="route('reservation.quotes.data', array_filter(['status' => $status?->value]))" :columns="$columns" :order="[[2, 'asc']]" :empty-text="__('No quotes found.')" />
    </x-card>
</x-layouts::app>
