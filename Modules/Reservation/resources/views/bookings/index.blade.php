<x-layouts::app :title="__('Reservations')" :subtitle="app(\App\Support\Tenancy\PropertyContext::class)->currentName()" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => null]">
    @can('reservation.booking.create')
        <x-slot:actions>
            <a href="{{ route('reservation.bookings.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New booking') }}</a>
        </x-slot:actions>
    @endcan

    <x-card>
        <form method="GET" action="{{ route('reservation.bookings.index') }}" class="row g-2 align-items-end mb-3" data-reservation-filters>
            <x-form.select name="status" :label="__('Status')" wrapper-class="col-sm-6 col-lg-2 mb-0" :search="false" :placeholder="__('Any status')"
                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()" :value="$filters['status']" />
            <x-form.select name="source" :label="__('Source')" wrapper-class="col-sm-6 col-lg-2 mb-0" :search="false" :placeholder="__('Any source')"
                :options="collect($sources)->mapWithKeys(fn ($source) => [$source->value => $source->label()])->all()" :value="$filters['source']" />
            <x-form.date name="from" :label="__('Arriving from')" wrapper-class="col-sm-6 col-lg-2 mb-0" :value="$filters['from']" />
            <x-form.date name="to" :label="__('Arriving to')" wrapper-class="col-sm-6 col-lg-2 mb-0" :value="$filters['to']" />
            <div class="col-lg-4 d-flex gap-2">
                <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('reservation.bookings.index') }}" class="btn btn-link">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>
        <p class="small text-body-secondary">{{ __('Search by booking number or guest (name, phone or email).') }}</p>
        <x-datatable id="reservations-table" :url="route('reservation.bookings.data', array_filter($filters))" :columns="$columns" :order="[[2, 'asc']]" :empty-text="__('No reservations found.')" />
    </x-card>
</x-layouts::app>
