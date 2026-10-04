@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $leavesToday = $reservation->checkOut <= $property->businessDate;
    $here = url()->current();
@endphp

<x-layouts::app :title="__('Check out :guest', ['guest' => $reservation->guestName])" :subtitle="$reservation->code"
    :breadcrumbs="[__('Front desk') => route('frontoffice.desk'), $reservation->code => route('reservation.bookings.show', $reservation->id), __('Check out') => null]">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <x-status-badge :status="$reservation->status" />
        <span class="text-body-secondary">{{ \Carbon\Carbon::parse($reservation->checkIn)->format('D d M') }} → {{ \Carbon\Carbon::parse($reservation->checkOut)->format('D d M Y') }} · {{ implode(', ', $reservation->units) }}</span>
    </div>

    @unless ($leavesToday)
        <div class="alert alert-warning" data-early>{{ __('This guest is due to leave on :date. To leave early, shorten the stay first (Change stay).', ['date' => \Carbon\Carbon::parse($reservation->checkOut)->format('D d M Y')]) }}</div>
    @endunless

    <div class="row">
        <div class="col-xl-7">
            {{-- 1. Room charges --}}
            <x-card :title="__('1. Room charges')" icon="bi-moon-stars">
                @if ($unposted > 0)
                    <p>{{ trans_choice(':count room night is not on the bill yet.|:count room nights are not on the bill yet.', $unposted) }}</p>
                    <form method="POST" action="{{ route('frontoffice.check-out.charges', $reservation->id) }}" data-post-charges>
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Post room charges') }}</button>
                    </form>
                @else
                    <p class="text-success mb-0" data-charges-posted><i class="bi bi-check-circle"></i> {{ __('Every room night is on the bill.') }}</p>
                @endif
            </x-card>

            {{-- 2. Folios --}}
            @foreach ($folios as $folio)
                <x-card :title="$folio->folioNo.' · '.$folio->name" icon="bi-receipt" data-checkout-folio="{{ $folio->folioNo }}">
                    <div class="d-flex justify-content-between mb-2">
                        <x-status-badge :status="$folio->type" />
                        <span>{{ __('Balance') }}: <strong class="font-monospace" data-folio-balance>{{ $folio->currencyCode }} {{ $money($folio->balance) }}</strong></span>
                    </div>
                    @if ($folio->status === \Modules\Billing\Enums\FolioStatus::Open && bccomp($folio->balance, '0', 2) > 0)
                        @can('billing.payment.create')
                            <form method="POST" action="{{ route('billing.payments.store') }}" class="row g-2 align-items-end mb-2" data-settle="{{ $folio->folioNo }}">
                                @csrf
                                <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                                <input type="hidden" name="folio_id" value="{{ $folio->id }}">
                                <input type="hidden" name="return_to" value="{{ $here }}">
                                <div class="col-sm-4"><select name="method" class="form-select" aria-label="{{ __('Method') }}">@foreach (\Modules\Billing\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach</select></div>
                                <div class="col-sm-4"><input type="number" name="amount" step="0.01" min="0.01" value="{{ $folio->balance }}" class="form-control" aria-label="{{ __('Amount') }}"></div>
                                <div class="col-sm-4"><button class="btn btn-success w-100"><i class="bi bi-cash"></i> {{ __('Take payment') }}</button></div>
                            </form>
                            <p class="small text-body-secondary">{{ __('Paying with two methods? Take part of the balance, then the rest.') }}</p>
                        @endcan
                        @can('billing.folio.post')
                            @if ($companies !== [])
                                <form method="POST" action="{{ route('billing.folios.transfer', $folio->id) }}" class="d-flex gap-2" data-to-ledger="{{ $folio->folioNo }}">
                                    @csrf
                                    <input type="hidden" name="return_to" value="{{ $here }}">
                                    <select name="company_id" class="form-select" aria-label="{{ __('Company') }}">
                                        @foreach ($companies as $company)<option value="{{ $company['id'] }}" @selected($folio->billTo === \Modules\Billing\Enums\BillTo::Company && $folio->billToId === $company['id'])>{{ $company['name'] }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-outline-info text-nowrap"><i class="bi bi-building"></i> {{ __('To city ledger') }}</button>
                                </form>
                            @endif
                        @endcan
                    @elseif ($folio->status === \Modules\Billing\Enums\FolioStatus::Open && bccomp($folio->balance, '0', 2) < 0)
                        <p class="text-warning-emphasis mb-0" data-credit-balance>{{ __('The guest has a credit of :amount: refund it from the Payments tab.', ['amount' => $money(bcmul($folio->balance, '-1', 2))]) }}
                            <a href="{{ route('reservation.bookings.show', $reservation->id) }}#payments">{{ __('Payments') }}</a></p>
                    @else
                        <p class="text-success mb-0"><i class="bi bi-check-circle"></i> {{ __('Settled.') }}</p>
                    @endif
                </x-card>
            @endforeach
        </div>

        <div class="col-xl-5">
            <x-card :title="__('3. Security deposit and refunds')" icon="bi-arrow-counterclockwise">
                <p class="small mb-0">{{ __('A security deposit is returned, and credits refunded, from the booking\'s Payments tab.') }}
                    <a href="{{ route('reservation.bookings.show', $reservation->id) }}#payments">{{ __('Open Payments') }}</a></p>
            </x-card>

            <x-card :title="__('4. Check out')" icon="bi-box-arrow-right">
                @unless ($settled)
                    <div class="alert alert-warning py-2 small" data-not-settled>{{ __('Settle every folio first.') }}</div>
                @endunless
                <form method="POST" action="{{ route('frontoffice.check-out.store', $reservation->id) }}" data-check-out-form>
                    @csrf
                    <button class="btn btn-warning btn-lg w-100" @disabled(! $settled || ! $leavesToday || $unposted > 0)><i class="bi bi-box-arrow-right"></i> {{ __('Check out and issue invoices') }}</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts::app>
