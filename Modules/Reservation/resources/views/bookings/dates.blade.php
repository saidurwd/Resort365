<x-layouts::app :title="__('New booking')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('New booking') => null]">
    @include('reservation::bookings.partials.steps')
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('reservation.bookings.create.store') }}" data-wizard-dates>
                @csrf
                <x-card :title="__('Dates and guests')" icon="bi-calendar-range">
                    <div class="row">
                        <div class="col-md-6"><x-form.date name="check_in" :label="__('Check-in')" :value="$values['check_in']" required /></div>
                        <div class="col-md-6"><x-form.date name="check_out" :label="__('Check-out')" :value="$values['check_out']" required /></div>
                        <div class="col-md-3"><x-form.input name="adults" type="number" min="1" :label="__('Adults')" :value="$values['adults']" required /></div>
                        <div class="col-md-3"><x-form.input name="children" type="number" min="0" :label="__('Children')" :value="$values['children']" /></div>
                        <div class="col-md-6"><x-form.select name="rate_plan" :label="__('Rate plan')" :options="$plans" :value="$values['rate_plan'] ?? array_key_first($plans)" required :search="false" /></div>
                    </div>
                </x-card>
                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary">{{ __('Find cottages and rooms') }} <i class="bi bi-arrow-right"></i></button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
