{{--
    The availability search (Step 8.2b): dates, adults, children and an optional promotion code, to /resort/{site}/book.
    $stay = ['check_in' => …, 'check_out' => …, 'adults' => …, 'children' => …, 'promo_code' => …] (optional); $target = '_top' in the widget.
--}}
@php($stay ??= ['check_in' => request('check_in'), 'check_out' => request('check_out'), 'adults' => request('adults', 2), 'children' => request('children', 0), 'promo_code' => request('promo_code')])
<form method="GET" action="{{ route('website.book', ['site' => $site->slug]) }}" class="website-search" @isset($target) target="{{ $target }}" @endisset data-search-form>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="search-check-in">{{ __('Arrival') }}</label>
            <input type="date" class="form-control" id="search-check-in" name="check_in" value="{{ $stay['check_in'] }}" min="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="search-check-out">{{ __('Departure') }}</label>
            <input type="date" class="form-control" id="search-check-out" name="check_out" value="{{ $stay['check_out'] }}" min="{{ now()->addDay()->toDateString() }}" required>
        </div>
        <div class="col-4 col-md-2">
            <label class="form-label small mb-1" for="search-adults">{{ __('Adults') }}</label>
            <select class="form-select" id="search-adults" name="adults">
                @foreach (range(1, 12) as $n)<option value="{{ $n }}" @selected((int) $stay['adults'] === $n)>{{ $n }}</option>@endforeach
            </select>
        </div>
        <div class="col-4 col-md-2">
            <label class="form-label small mb-1" for="search-children">{{ __('Children') }}</label>
            <select class="form-select" id="search-children" name="children">
                @foreach (range(0, 8) as $n)<option value="{{ $n }}" @selected((int) $stay['children'] === $n)>{{ $n }}</option>@endforeach
            </select>
        </div>
        <div class="col-4 col-md-2 d-grid">
            <button type="submit" class="btn btn-website-primary"><i class="bi bi-search"></i> {{ __('Search') }}</button>
        </div>
        @empty($compact)
            <div class="col-md-4">
                <label class="form-label small mb-1" for="search-promo">{{ __('Promotion code') }}</label>
                <input type="text" class="form-control" id="search-promo" name="promo_code" value="{{ $stay['promo_code'] }}" maxlength="30">
            </div>
        @endempty
    </div>
</form>
