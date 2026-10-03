@php($newGuest = $state['new_guest'] ?? null)

<x-layouts::app :title="__('New booking')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('New booking') => null]">
    @include('reservation::bookings.partials.steps')
    @if ($quoteError)<div class="alert alert-danger">{{ $quoteError }}</div>@endif
    @if ($blacklisted)<div class="alert alert-danger"><i class="bi bi-slash-circle"></i> {{ __('This guest is blacklisted.') }}</div>@endif

    @if ($quote)
        <div class="row">
            <div class="col-xl-7">
                <x-card :title="__('Booking')" icon="bi-journal-check" body-class="p-0">
                    <table class="table mb-0" data-confirm-items>
                        <thead><tr><th class="ps-3">{{ __('Item') }}</th><th>{{ __('Guests') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                        <tbody>
                            @foreach ($quote->items as $line)
                                <tr>
                                    <td class="ps-3">{{ $labels[($line->item->type->value).':'.$line->item->unitId] ?? $line->label }}</td>
                                    <td>{{ trans_choice(':count adult|:count adults', $line->item->adults) }}@if ($line->item->children), {{ trans_choice(':count child|:count children', $line->item->children) }}@endif</td>
                                    <td class="text-end pe-3 font-monospace">{{ number_format((float) $line->quote->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                <x-card :title="__('Guest')" icon="bi-person">
                    @if ($guest)
                        <div class="fw-semibold">{{ $guest->name }}</div><div class="small text-body-secondary">{{ collect([$guest->phone, $guest->email])->filter()->implode(' · ') }}</div>
                    @elseif ($newGuest)
                        <div class="fw-semibold">{{ trim($newGuest['first_name'].' '.($newGuest['last_name'] ?? '')) }} <span class="badge text-bg-info">{{ __('new guest') }}</span></div>
                        <div class="small text-body-secondary">{{ collect([$newGuest['phone'] ?? null, $newGuest['email'] ?? null])->filter()->implode(' · ') }}</div>
                    @endif
                    @if (! empty($state['special_requests']))<div class="mt-2 small"><span class="text-body-secondary">{{ __('Requests:') }}</span> {{ $state['special_requests'] }}</div>@endif
                </x-card>
            </div>
            <div class="col-xl-5">
                <x-card :title="__('Price and deposit')" icon="bi-cash-stack">
                    @include('reservation::bookings.partials.totals', ['quote' => $quote])
                    @if ($quote->deposit->dueAt)
                        <div class="small mt-2" data-deposit-due>{{ __('The booking is held as Tentative. The deposit is due by :time.', ['time' => \Carbon\Carbon::parse($quote->deposit->dueAt)->setTimezone($timezone)->format('d M Y H:i')]) }}
                            @if ($quote->deposit->autoCancelUnpaid) {{ __('Unpaid, it is cancelled automatically.') }}@endif</div>
                    @else
                        <div class="small mt-2">{{ __('No deposit is due: the booking is confirmed at once.') }}</div>
                    @endif
                </x-card>
                <form method="POST" action="{{ route('reservation.bookings.store') }}" data-wizard-confirm>
                    @csrf
                    <button type="submit" class="btn btn-success btn-lg w-100 mb-4"><i class="bi bi-check2-circle"></i> {{ __('Create booking') }}</button>
                </form>
            </div>
        </div>
    @endif
    <a href="{{ route('reservation.bookings.pricing') }}" class="btn btn-outline-secondary mb-4"><i class="bi bi-arrow-left"></i> {{ __('Back') }}</a>
    {{-- TODO(step-1.7): take the deposit here; TODO(step-8.3): send a payment link. --}}
</x-layouts::app>
