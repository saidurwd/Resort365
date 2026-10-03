@php
    $duplicates = session('guest_duplicates', []);
    $mode = old('guest_mode', isset($state['new_guest']) ? 'new' : 'existing');
    $newGuest = $state['new_guest'] ?? [];
@endphp

<x-layouts::app :title="__('New booking')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('New booking') => null]">
    @include('reservation::bookings.partials.steps')
    <form method="POST" action="{{ route('reservation.bookings.guest.store') }}" x-data="{ mode: @js($mode) }" data-wizard-guest>
        @csrf
        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Guest')" icon="bi-person">
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="guest_mode" value="existing" id="mode-existing" x-model="mode">
                            <label class="form-check-label" for="mode-existing">{{ __('Existing guest') }}</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="guest_mode" value="new" id="mode-new" x-model="mode">
                            <label class="form-check-label" for="mode-new">{{ __('New guest') }}</label>
                        </div>
                    </div>

                    <div x-show="mode === 'existing'">
                        <x-form.select name="guest_id" :label="__('Guest')" :options="$chosenGuest ? [$chosenGuest->id => $chosenGuest->name.($chosenGuest->phone ? ' · '.$chosenGuest->phone : '')] : []"
                            :value="$chosenGuest?->id" :placeholder="__('Type a name, phone, email or ID…')" :tom-options="['remote' => route('guest.guests.search')]" />
                        @if ($chosenGuest?->isBlacklisted)
                            <div class="alert alert-danger py-2"><i class="bi bi-slash-circle"></i> {{ __('This guest is blacklisted: :reason', ['reason' => $chosenGuest->blacklistReason]) }}</div>
                        @endif
                    </div>

                    <div x-show="mode === 'new'" x-cloak>
                        @if ($duplicates !== [])
                            <div class="alert alert-warning" data-guest-duplicates>
                                <div class="fw-semibold">{{ __('This guest may already exist') }}</div>
                                <ul class="mb-2">
                                    @foreach ($duplicates as $duplicate)
                                        <li>{{ $duplicate['name'] }} <span class="text-body-secondary">{{ collect([$duplicate['phone'], $duplicate['email']])->filter()->implode(' · ') }}</span></li>
                                    @endforeach
                                </ul>
                                <div class="small mb-2">{{ __('Choose "Existing guest" and search for them, or confirm this is someone else.') }}</div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="confirm_new" value="1" id="field-confirm_new">
                                    <label class="form-check-label" for="field-confirm_new">{{ __('This is a different person') }}</label>
                                </div>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6"><x-form.input name="first_name" :label="__('First name')" :value="$newGuest['first_name'] ?? null" /></div>
                            <div class="col-md-6"><x-form.input name="last_name" :label="__('Last name')" :value="$newGuest['last_name'] ?? null" /></div>
                            <div class="col-md-6"><x-form.input name="phone" type="tel" :label="__('Phone')" :value="$newGuest['phone'] ?? null" /></div>
                            <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" :value="$newGuest['email'] ?? null" /></div>
                        </div>
                        <p class="small text-body-secondary mb-0">{{ __('More details (ID, address) can be added on the guest\'s profile later.') }}</p>
                    </div>
                </x-card>

                <x-card :title="__('Booked by')" icon="bi-building">
                    <div class="row">
                        <div class="col-md-4"><x-form.select name="source" :label="__('Source')" :options="\Modules\Reservation\Enums\ReservationSource::options()" :value="$state['source'] ?? 'front_desk'" required :search="false" /></div>
                        <div class="col-md-4"><x-form.select name="company_id" :label="__('Company')" :options="$companies" :value="$state['company_id'] ?? null" :placeholder="__('None')" /></div>
                        <div class="col-md-4"><x-form.select name="travel_agent_id" :label="__('Travel agent')" :options="$travelAgents" :value="$state['travel_agent_id'] ?? null" :placeholder="__('None')" /></div>
                    </div>
                </x-card>
            </div>
        </div>
        <div class="d-flex justify-content-between mb-4">
            <a href="{{ route('reservation.bookings.choose') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Continue') }} <i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
</x-layouts::app>
