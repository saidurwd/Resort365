@extends('website::public.layout')

@section('title', __('Your details'))

@php
    use Modules\Website\Services\Prices;
    $date = fn (?string $day): string => $day ? \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale())->translatedFormat('D j M Y') : '';
    $field = fn (string $name): string => $errors->has($name) ? 'form-control is-invalid' : 'form-control';
@endphp

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Your details') }}</h1>
            <p class="lead mb-0">{{ $date($cart['check_in']) }} – {{ $date($cart['check_out']) }} · {{ $ratePlan?->name }}</p>
        </div>
    </section>
    <section class="website-section">
        <div class="container">
            <form method="POST" action="{{ route('website.checkout.store', ['site' => $site->slug]) }}" class="row g-4" data-checkout-form novalidate>
                @csrf
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="first_name">{{ __('First name') }} *</label>
                            <input class="{{ $field('first_name') }}" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="last_name">{{ __('Last name') }} *</label>
                            <input class="{{ $field('last_name') }}" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="email">{{ __('Email') }} *</label>
                            <input type="email" class="{{ $field('email') }}" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="phone">{{ __('Mobile phone') }} *</label>
                            <input type="tel" class="{{ $field('phone') }}" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="nationality_code">{{ __('Nationality') }}</label>
                            <select class="form-select" id="nationality_code" name="nationality_code">
                                <option value="">—</option>
                                @foreach ($countries as $code => $country)
                                    <option value="{{ $code }}" @selected(old('nationality_code') === $code)>{{ $country }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="special_requests">{{ __('Special requests') }}</label>
                            <textarea class="{{ $field('special_requests') }}" id="special_requests" name="special_requests" rows="3">{{ old('special_requests') }}</textarea>
                        </div>
                        {{-- A field people never see: robots fill it in. --}}
                        <div class="website-hp" aria-hidden="true">
                            <label for="website_url">{{ __('Leave this empty') }}</label>
                            <input id="website_url" name="website_url" tabindex="-1" autocomplete="off">
                        </div>
                    </div>

                    <h2 class="h5 mt-4">{{ __('Payment') }}</h2>
                    @foreach ($choices as $value => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="pay_choice" id="pay-{{ $value }}" value="{{ $value }}" @checked(old('pay_choice', 'deposit') === $value)>
                            <label class="form-check-label" for="pay-{{ $value }}">
                                {{ $label }}:
                                <strong>{{ Prices::format($value === 'full' || (float) $quote->deposit->amount === 0.0 ? $quote->total : $quote->deposit->amount, $currency) }}</strong>
                            </label>
                        </div>
                    @endforeach
                    @error('pay_choice')<div class="text-danger small">{{ $message }}</div>@enderror
                    <p class="small text-body-secondary mt-2">{{ __('You will see how to pay on the next page and in your email.') }}</p>

                    <h2 class="h5 mt-4">{{ __('Policies') }}</h2>
                    <div class="website-policy">
                        <h3 class="h6">{{ __('Deposit and payment') }}</h3>
                        <ul>@foreach ($depositTerms as $line)<li>{{ $line }}</li>@endforeach</ul>
                        <h3 class="h6">{{ __('Cancellation') }}</h3>
                        <ul class="mb-0">@foreach ($cancellationTerms as $line)<li>{{ $line }}</li>@endforeach</ul>
                    </div>
                    <div class="form-check mt-3">
                        <input @class(['form-check-input', 'is-invalid' => $errors->has('accept_policies')]) type="checkbox" name="accept_policies" id="accept_policies" value="1" @checked(old('accept_policies'))>
                        <label class="form-check-label" for="accept_policies">{{ __('I accept the deposit, cancellation and house policies.') }}</label>
                        @error('accept_policies')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <aside class="col-lg-5">
                    <div class="website-cart">
                        <h2 class="h5">{{ __('Your booking') }}</h2>
                        <ul class="list-unstyled website-cart-items">
                            @foreach ($quote->items as $line)
                                <li class="d-flex justify-content-between"><span>{{ $line->label }}</span><span>{{ Prices::format($line->quote->total, $currency) }}</span></li>
                            @endforeach
                        </ul>
                        <dl class="website-cart-totals">
                            @if ((float) $quote->discount > 0)
                                <dt>{{ __('Discount') }}</dt><dd>-{{ Prices::format($quote->discount, $currency) }}</dd>
                            @endif
                            <dt>{{ __('Taxes') }}</dt><dd>{{ Prices::format($quote->tax, $currency) }}</dd>
                            <dt class="fw-bold">{{ __('Total') }}</dt><dd class="fw-bold">{{ Prices::format($quote->total, $currency) }}</dd>
                            <dt>{{ __('Deposit to confirm') }}</dt><dd>{{ Prices::format($quote->deposit->amount, $currency) }}</dd>
                        </dl>
                        <button type="submit" class="btn btn-website-primary btn-lg w-100" data-book>{{ __('Book now') }}</button>
                        <a class="d-block text-center mt-2 small" href="{{ route('website.book', ['site' => $site->slug]) }}">{{ __('Change your booking') }}</a>
                    </div>
                </aside>
            </form>
        </div>
    </section>
@endsection
