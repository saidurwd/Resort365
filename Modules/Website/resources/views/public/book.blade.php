@extends('website::public.layout')

@section('title', __('Book your stay'))

@php
    use Modules\Website\Services\Prices;
    $nights = $searched ? (int) \Carbon\CarbonImmutable::parse($stay['check_in'])->diffInDays(\Carbon\CarbonImmutable::parse($stay['check_out'])) : 0;
    $quote = $priced['quote'];
    $date = fn (?string $day): string => $day ? \Carbon\CarbonImmutable::parse($day)->locale(app()->getLocale())->translatedFormat('D j M Y') : '';
@endphp

@section('content')
    <section class="website-page-head">
        <div class="container">
            <h1>{{ __('Book your stay') }}</h1>
            @include('website::public.partials.search', ['stay' => $stay])
        </div>
    </section>

    <section class="website-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8" data-results>
                    @if (! $searched)
                        <p class="text-body-secondary">{{ __('Choose your dates and the number of guests to see what is free.') }}</p>
                    @elseif ($results === [])
                        <div class="website-policy" data-no-results>
                            <h2 class="h5">{{ __('Nothing is free for these dates') }}</h2>
                            <p class="mb-0">{{ __('Try other dates, or call us and we will look for you.') }}</p>
                        </div>
                    @else
                        <p class="text-body-secondary">{{ trans_choice('{1} :from to :to, 1 night|[2,*] :from to :to, :count nights', $nights, ['from' => $date($stay['check_in']), 'to' => $date($stay['check_out']), 'count' => $nights]) }}</p>
                        @foreach ($results as $result)
                            <h2 class="h4 mt-4" data-plan="{{ $result->ratePlan->code }}">{{ $result->ratePlan->name }}</h2>
                            @php($options = [
                                ...array_map(fn ($option) => ['type' => 'room', 'id' => $option->roomType->id, 'key' => 'room_type:'.$option->roomType->id, 'name' => $option->roomType->name, 'detail' => trans_choice('{1} 1 room left|[2,*] :count rooms left', count($option->rooms), ['count' => count($option->rooms)]), 'option' => $option], $result->roomTypes),
                                ...array_map(fn ($option) => ['type' => 'cottage', 'id' => $option->cottage->id, 'key' => 'cottage_type:'.$option->cottage->cottageTypeId, 'name' => $option->cottage->name, 'detail' => $option->typeName.' · '.__('the whole cottage'), 'option' => $option], $result->cottages),
                            ])
                            @foreach ($options as $row)
                                @php($option = $row['option'])
                                @php($unit = $units[$row['key']] ?? null)
                                <div class="website-result" data-result="{{ $row['type'] }}:{{ $row['id'] }}">
                                    <div class="website-result-photo">
                                        @if ($unit && $unit['photos'] !== [])
                                            <img src="{{ $unit['photos'][0]['thumb'] }}" alt="{{ $row['name'] }}" loading="lazy" width="480" height="320">
                                        @else
                                            <span class="website-card-placeholder"><i class="bi bi-house-heart"></i></span>
                                        @endif
                                    </div>
                                    <div class="website-result-body">
                                        <h3 class="h5 mb-1">{{ $row['name'] }}</h3>
                                        <p class="small text-body-secondary mb-2">{{ $row['detail'] }}</p>
                                        @if (! $option->fits)
                                            <p class="small text-danger mb-0"><i class="bi bi-people"></i> {{ __('Too many guests for this one.') }}</p>
                                        @elseif (! $option->isBookable())
                                            <p class="small text-danger mb-0"><i class="bi bi-calendar-x"></i> {{ implode(' ', array_map(fn ($violation) => $violation->message(), $option->violations)) ?: __('Not available for these dates.') }}</p>
                                        @endif
                                    </div>
                                    <div class="website-result-price">
                                        @if ($option->quote)
                                            <div class="website-price-amount">{{ Prices::format($option->quote->total, $currency) }}</div>
                                            <div class="small text-body-secondary">{{ trans_choice('{1} for 1 night|[2,*] for :count nights', $nights, ['count' => $nights]) }} · {{ __('taxes included') }}</div>
                                            @if ($option->quote->promotion)
                                                <div class="small text-success">{{ $option->quote->promotion->name }}</div>
                                            @endif
                                        @endif
                                        @if ($option->fits && $option->isBookable())
                                            <form method="POST" action="{{ route('website.cart.add', ['site' => $site->slug]) }}" class="mt-2">
                                                @csrf
                                                @foreach (['check_in', 'check_out', 'adults', 'children', 'promo_code'] as $field)
                                                    <input type="hidden" name="{{ $field }}" value="{{ $stay[$field] }}">
                                                @endforeach
                                                <input type="hidden" name="rate_plan_id" value="{{ $result->ratePlan->id }}">
                                                <input type="hidden" name="type" value="{{ $row['type'] }}">
                                                <input type="hidden" name="id" value="{{ $row['id'] }}">
                                                <button type="submit" class="btn btn-website-primary btn-sm" data-add="{{ $row['type'] }}:{{ $row['id'] }}"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @endif
                </div>

                <aside class="col-lg-4" id="cart">
                    <div class="website-cart" data-cart>
                        <h2 class="h5">{{ __('Your booking') }}</h2>
                        @if ($cart['items'] === [])
                            <p class="text-body-secondary mb-0" data-cart-empty>{{ __('Add a room or a cottage to start.') }}</p>
                        @else
                            <p class="small text-body-secondary">{{ $date($cart['check_in']) }} – {{ $date($cart['check_out']) }}</p>
                            <ul class="list-unstyled website-cart-items">
                                @foreach ($cart['items'] as $index => $item)
                                    @php($line = $quote?->items[$index] ?? null)
                                    <li data-cart-item="{{ $index }}">
                                        <div class="d-flex justify-content-between gap-2">
                                            <div>
                                                <div class="fw-semibold">{{ $line?->label ?? ($item['type'] === 'room' ? ($units['room_type:'.$item['id']]['name'] ?? __('Room')) : __('Cottage')) }}</div>
                                                <div class="small text-body-secondary">{{ trans_choice('{1} 1 adult|[2,*] :count adults', $item['adults'], ['count' => $item['adults']]) }}@if ($item['children'] > 0), {{ trans_choice('{1} 1 child|[2,*] :count children', $item['children'], ['count' => $item['children']]) }}@endif</div>
                                                @isset($priced['problems'][$index])
                                                    <div class="small text-danger" data-cart-problem>{{ $priced['problems'][$index] }}</div>
                                                @endisset
                                            </div>
                                            <div class="text-end">
                                                @if ($line)<div>{{ Prices::format($line->quote->total, $currency) }}</div>@endif
                                                <form method="POST" action="{{ route('website.cart.remove', ['site' => $site->slug, 'index' => $index]) }}">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-link btn-sm p-0" data-remove="{{ $index }}">{{ __('Remove') }}</button>
                                                </form>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            @isset($priced['problems'][-1])
                                <div class="small text-danger mb-2">{{ $priced['problems'][-1] }}</div>
                            @endisset
                            @if ($quote)
                                <dl class="website-cart-totals" data-cart-total="{{ $quote->total }}">
                                    @if ((float) $quote->discount > 0)
                                        <dt>{{ __('Discount') }}</dt><dd>-{{ Prices::format($quote->discount, $currency) }}</dd>
                                    @endif
                                    <dt>{{ __('Taxes') }}</dt><dd>{{ Prices::format($quote->tax, $currency) }}</dd>
                                    <dt class="fw-bold">{{ __('Total') }}</dt><dd class="fw-bold">{{ Prices::format($quote->total, $currency) }}</dd>
                                    <dt>{{ __('Deposit to confirm') }}</dt><dd>{{ Prices::format($quote->deposit->amount, $currency) }}</dd>
                                </dl>
                                <a class="btn btn-website-primary w-100" href="{{ route('website.checkout', ['site' => $site->slug]) }}" data-checkout>{{ __('Continue to book') }} <i class="bi bi-arrow-right"></i></a>
                            @endif
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
