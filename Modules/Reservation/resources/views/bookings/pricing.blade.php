<x-layouts::app :title="__('New booking')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('New booking') => null]">
    @include('reservation::bookings.partials.steps')
    <form method="POST" action="{{ route('reservation.bookings.pricing.store') }}" data-wizard-pricing>
        @csrf
        @if ($quoteError)<div class="alert alert-danger">{{ $quoteError }}</div>@endif
        <div class="row">
            <div class="col-xl-7">
                <x-card :title="__('Guests per room or cottage')" icon="bi-people" body-class="p-0">
                    <table class="table align-middle mb-0" data-booking-items>
                        <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Adults') }}</th><th>{{ __('Children') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($items as $key => $item)
                                @php($line = $quote ? collect($quote->items)->first(fn ($line) => $line->item->type === $item['type'] && $line->item->unitId === $item['id']) : null)
                                <tr data-item="{{ $key }}">
                                    <td class="ps-3">{{ $labels[$key] ?? $key }}
                                        @if ($line)@include('reservation::availability.partials.nights', ['quote' => $line->quote])@endif
                                    </td>
                                    <td class="w-auto"><input type="number" min="1" class="form-control form-control-sm" name="occupancy[{{ $key }}][adults]" value="{{ $item['adults'] }}" aria-label="{{ __('Adults') }}"></td>
                                    <td class="w-auto"><input type="number" min="0" class="form-control form-control-sm" name="occupancy[{{ $key }}][children]" value="{{ $item['children'] }}" aria-label="{{ __('Children') }}"></td>
                                    <td class="text-end pe-3 font-monospace">{{ $line ? number_format((float) $line->quote->total, 2) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                <x-card :title="__('Notes')" icon="bi-chat-left-text">
                    <x-form.field name="special_requests" :label="__('Special requests (guest)')">
                        <textarea name="special_requests" id="field-special_requests" rows="2" class="form-control">{{ old('special_requests', $state['special_requests'] ?? '') }}</textarea>
                    </x-form.field>
                    <x-form.field name="internal_notes" :label="__('Internal notes (staff only)')">
                        <textarea name="internal_notes" id="field-internal_notes" rows="2" class="form-control">{{ old('internal_notes', $state['internal_notes'] ?? '') }}</textarea>
                    </x-form.field>
                </x-card>
            </div>
            <div class="col-xl-5">
                <x-card :title="__('Price and deposit')" icon="bi-cash-stack">
                    <div class="row">
                        <div class="col-6"><x-form.input name="promo_code" :label="__('Promo code')" :value="$state['promo_code'] ?? null" /></div>
                        <div class="col-6">
                            @php($terms = $quote?->depositPolicy?->terms)
                            <x-form.input name="deposit_percent" :label="__('Deposit %')" :value="$state['deposit_percent'] ?? ($terms ? rtrim(rtrim($terms->defaultPercent, '0'), '.') : null)" inputmode="decimal"
                                :help="$terms ? ($terms->minPercent !== null || $terms->maxPercent !== null
                                    ? __('Policy: :min–:max%, default :default%', ['min' => rtrim(rtrim($terms->minPercent ?? '0.00', '0'), '.'), 'max' => rtrim(rtrim($terms->maxPercent ?? '100.00', '0'), '.'), 'default' => rtrim(rtrim($terms->defaultPercent, '0'), '.')])
                                    : __('Negotiable; default :default%', ['default' => rtrim(rtrim($terms->defaultPercent, '0'), '.')])) : __('No deposit policy')" />
                        </div>
                    </div>
                    @if ($quote)
                        @include('reservation::bookings.partials.totals', ['quote' => $quote])
                        @unless ($quote->depositWithinLimits)
                            <div class="alert alert-warning small mt-2 mb-0" data-deposit-override>{{ __('This deposit is outside the policy.') }} @can('reservation.deposit.override'){{ __('You may allow it.') }}@else{{ __('A manager must allow it.') }}@endcan</div>
                        @endunless
                    @endif
                    <button type="submit" name="action" value="recalculate" class="btn btn-outline-primary w-100 mt-3"><i class="bi bi-arrow-repeat"></i> {{ __('Recalculate') }}</button>
                </x-card>
            </div>
        </div>
        <div class="d-flex justify-content-between mb-4">
            <a href="{{ route('reservation.bookings.guest') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
            <button type="submit" name="action" value="continue" class="btn btn-primary" @disabled(! $quote)>{{ __('Continue') }} <i class="bi bi-arrow-right"></i></button>
        </div>
    </form>
</x-layouts::app>
