<x-layouts::guest :title="__('Start your free trial')" wide>
    <h1 class="h4 mb-1">{{ __('Start your free trial') }}</h1>
    <p class="text-body-secondary">{{ __(':days days free, no card needed. Your resort gets its own address; set it up in a few steps and take your first booking today.', ['days' => $trialDays]) }}</p>

    <form method="POST" action="{{ route('platform.signup.store') }}" data-signup-form>
        @csrf
        <div class="visually-hidden" aria-hidden="true">
            <label for="field-website">{{ __('Leave this empty') }}</label>
            <input type="text" name="website" id="field-website" tabindex="-1" autocomplete="off">
        </div>

        <h2 class="h6 text-uppercase text-body-secondary mt-3">{{ __('Your company') }}</h2>
        <x-form.input name="company" :label="__('Company or resort name')" required autofocus autocomplete="organization" />
        <div x-data="subdomainCheck(@js(route('platform.signup.check')))">
            <x-form.input name="slug" :label="__('Your address')" required :append="'.'.$domain" class="subdomain-input" autocomplete="off" x-model="slug" x-on:input.debounce.400ms="check()"
                :help="__('Lowercase letters, numbers and hyphens, e.g. rodela.')" />
            <div class="small mb-3" x-show="message" x-cloak x-bind:class="available ? 'text-success' : 'text-danger'" x-text="message" role="status"></div>
        </div>

        <h2 class="h6 text-uppercase text-body-secondary mt-3">{{ __('You') }}</h2>
        <div class="row">
            <div class="col-md-6"><x-form.input name="name" :label="__('Your name')" required autocomplete="name" /></div>
            <div class="col-md-6"><x-form.input name="email" type="email" :label="__('Email')" required autocomplete="email" /></div>
            <div class="col-md-6"><x-form.input name="password" type="password" :label="__('Password')" required autocomplete="new-password" /></div>
            <div class="col-md-6"><x-form.input name="password_confirmation" type="password" :label="__('Password again')" required autocomplete="new-password" /></div>
        </div>

        <h2 class="h6 text-uppercase text-body-secondary mt-3">{{ __('Plan') }}</h2>
        <p class="small text-body-secondary">{{ __('Try any plan free; you can change it later. After the trial you get an invoice, payable by bank transfer.') }}</p>
        <div class="list-group mb-3" role="radiogroup" aria-label="{{ __('Plan') }}">
            @foreach ($plans as $plan)
                <label class="list-group-item d-flex gap-3">
                    <input class="form-check-input flex-shrink-0 mt-1" type="radio" name="plan_id" value="{{ $plan->id }}" @checked((int) old('plan_id', $chosen?->id) === $plan->id) required>
                    <span class="flex-grow-1">
                        <span class="d-flex justify-content-between gap-2">
                            <strong>{{ $plan->name }}</strong>
                            <span class="text-nowrap">{{ $plan->currency_code }} {{ $plan->monthly_price }} {{ __('a month') }} · {{ $plan->currency_code }} {{ $plan->yearly_price }} {{ __('a year') }}</span>
                        </span>
                        <span class="d-block small text-body-secondary">{{ $plan->description }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('plan_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <x-form.select name="billing_period" :label="__('Billed')" :options="$periods" value="monthly" required />

        <div class="form-check mb-3">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="field-terms" value="1" @checked(old('terms')) required>
            <label class="form-check-label" for="field-terms">{{ __('I agree to the terms of service and the privacy policy.') }}</label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <button type="submit" class="btn btn-primary w-100">{{ __('Create my resort') }}</button>
    </form>
</x-layouts::guest>
