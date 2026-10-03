<x-layouts::app :title="__('Availability')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Availability') => null]">
    <x-card>
        <form method="GET" action="{{ route('reservation.availability') }}" class="row g-2 align-items-end" data-availability-form>
            <div class="col-md-2"><x-form.date name="check_in" :label="__('Check-in')" :value="$values['check_in']" required wrapper-class="mb-0" /></div>
            <div class="col-md-2"><x-form.date name="check_out" :label="__('Check-out')" :value="$values['check_out']" required wrapper-class="mb-0" /></div>
            <div class="col-md-1"><x-form.input name="adults" type="number" min="1" :label="__('Adults')" :value="$values['adults']" required wrapper-class="mb-0" /></div>
            <div class="col-md-1"><x-form.input name="children" type="number" min="0" :label="__('Children')" :value="$values['children']" wrapper-class="mb-0" /></div>
            <div class="col-md-3"><x-form.select name="rate_plan" :label="__('Rate plan')" :options="collect($plans)->mapWithKeys(fn ($plan) => [$plan->id => $plan->name])->all()" :value="$values['rate_plan']" required :search="false" wrapper-class="mb-0" /></div>
            <div class="col-md-2"><x-form.input name="promo_code" :label="__('Promo code')" :value="$values['promo_code'] ?? null" wrapper-class="mb-0" /></div>
            <div class="col-md-1"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> {{ __('Search') }}</button></div>
        </form>
    </x-card>

    @if ($result)
        @php
            $search = $result->search;
            $planBlocked = $result->planViolations !== [];
        @endphp
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-search-summary>
            <span class="fw-semibold">{{ $search->checkIn->format('D d M Y') }} → {{ $search->checkOut->format('D d M Y') }}</span>
            <span class="text-body-secondary">· {{ trans_choice(':count night|:count nights', $search->nights()) }} · {{ trans_choice(':count adult|:count adults', $search->occupancy->adults) }}@if ($search->occupancy->children), {{ trans_choice(':count child|:count children', $search->occupancy->children) }}@endif · {{ $result->ratePlan->name }} · {{ __('prices in :currency', ['currency' => $currency]) }}{{ $result->ratePlan->pricesIncludeTax ? ', '.__('taxes included') : ', '.__('with taxes') }}</span>
            @include('reservation::availability.partials.violations', ['violations' => $result->planViolations])
        </div>

        <div class="row">
            <div class="col-xl-6">
                <x-card :title="__('Whole cottages')" icon="bi-houses" body-class="p-0">
                    @forelse ($result->cottages as $option)
                        <div @class(['px-3 py-2 border-bottom', 'opacity-50' => ! $option->isBookable() || $planBlocked]) data-cottage-option="{{ $option->cottage->code }}">
                            <div class="d-flex gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $option->cottage->name }} <span class="small text-body-secondary">{{ $option->cottage->code }} · {{ $option->typeName }}</span></div>
                                <div class="small text-body-secondary">
                                    {{ trans_choice(':count room|:count rooms', count($option->roomNumbers)) }} ({{ implode(', ', $option->roomNumbers) }}) · {{ __('up to :count guests', ['count' => $option->cottage->maxOccupancy]) }}
                                    @unless ($option->fits)<span class="badge text-bg-warning">{{ __('Too small for the party') }}</span>@endunless
                                </div>
                                @include('reservation::availability.partials.violations', ['violations' => $option->violations])
                            </div>
                            <div class="text-end text-nowrap">@include('reservation::availability.partials.price', ['quote' => $option->quote])</div>
                            </div>
                            @if ($option->quote)@include('reservation::availability.partials.nights', ['quote' => $option->quote])@endif
                        </div>
                    @empty
                        <x-empty-state icon="bi-houses" :title="__('No whole cottage is free for these dates')" class="py-4" />
                    @endforelse
                </x-card>
            </div>
            <div class="col-xl-6">
                <x-card :title="__('Rooms')" icon="bi-door-open" body-class="p-0">
                    @forelse ($result->roomTypes as $option)
                        <div @class(['px-3 py-2 border-bottom', 'opacity-50' => ! $option->isBookable() || $planBlocked]) data-room-option="{{ $option->roomType->code }}">
                            <div class="d-flex gap-3">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $option->roomType->name }} <span class="badge text-bg-success">{{ trans_choice(':count free|:count free', count($option->rooms)) }}</span></div>
                                <div class="small text-body-secondary">
                                    {{ __('Rooms :numbers', ['numbers' => implode(', ', array_map(fn ($room) => $room->number, $option->rooms))]) }}
                                    · {{ __('up to :count guests', ['count' => $option->roomType->maxOccupancy]) }}
                                    @unless ($option->fits)<span class="badge text-bg-warning">{{ __('Party needs more than one room') }}</span>@endunless
                                </div>
                                @include('reservation::availability.partials.violations', ['violations' => $option->violations])
                            </div>
                            <div class="text-end text-nowrap">@include('reservation::availability.partials.price', ['quote' => $option->quote])
                                <div class="small text-body-secondary">{{ __('per room') }}</div>
                            </div>
                            </div>
                            @if ($option->quote)@include('reservation::availability.partials.nights', ['quote' => $option->quote])@endif
                        </div>
                    @empty
                        <x-empty-state icon="bi-door-open" :title="__('No room is free for these dates')" class="py-4" />
                    @endforelse
                </x-card>
            </div>
        </div>
        {{-- TODO(step-1.6): choose options here and continue in the booking wizard. --}}
    @elseif ($plans === [])
        <x-card><x-empty-state icon="bi-tags" :title="__('No rate plan sells at the front desk yet')" :message="__('Create one under Rates → Rate plans.')" /></x-card>
    @endif
</x-layouts::app>
