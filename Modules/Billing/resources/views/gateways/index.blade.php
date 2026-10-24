<x-layouts::app :title="__('Online payments')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Online payments') => null]">
    <p class="text-body-secondary">{{ __('Guests pay deposits and balances online through the gateways switched on here: on the website after booking, and from payment links. Credentials are stored encrypted and never shown again; leave a field empty to keep it.') }}</p>

    <div class="row">
        @foreach ($providers as $provider)
            @php($gateway = $gateways->get($provider->value))
            <div class="col-lg-6">
                <form method="POST" action="{{ route('billing.gateways.update', ['provider' => $provider->value]) }}" data-gateway="{{ $provider->value }}">
                    @csrf
                    @method('PUT')
                    <x-card :title="$provider->label()" icon="bi-credit-card">
                        @if ($gateway?->is_enabled)
                            <span class="badge text-bg-success mb-2" data-gateway-on>{{ __('Switched on') }}</span>
                            <x-status-badge :status="$gateway->mode" />
                        @endif
                        <x-form.select name="mode" :label="__('Mode')" :options="$modes" :value="old('mode', $gateway?->mode->value ?? 'sandbox')" :search="false" required />
                        @foreach ($provider->credentialFields() as $field => [$label, $secret])
                            <x-form.input :name="'credentials['.$field.']'" :type="$secret ? 'password' : 'text'" :label="$label"
                                :help="filled($gateway?->credentials[$field] ?? null) ? __('Saved. Leave empty to keep it.') : null" autocomplete="off" />
                        @endforeach
                        @if ($provider === \Modules\Billing\Enums\GatewayProvider::Stripe && $gateway)
                            <p class="small">{{ __('Webhook address for Stripe (event checkout.session.completed):') }} <code class="text-break">{{ route('billing.online.notify', ['provider' => 'stripe', 'gateway' => $gateway->id]) }}</code></p>
                        @endif
                        @if ($provider === \Modules\Billing\Enums\GatewayProvider::Test)
                            <p class="small text-body-secondary">{{ __('Pretends to be a gateway so the whole payment can be tried; never available in production.') }}</p>
                        @endif
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_enabled" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_enabled" value="1" id="enabled-{{ $provider->value }}" @checked($gateway?->is_enabled)>
                            <label class="form-check-label" for="enabled-{{ $provider->value }}">{{ __('Guests can pay through this gateway') }}</label>
                        </div>
                        @error('credentials')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <x-slot:footer>
                            <div class="d-flex justify-content-end"><button class="btn btn-primary btn-sm">{{ __('Save') }}</button></div>
                        </x-slot:footer>
                    </x-card>
                </form>
            </div>
        @endforeach
    </div>

    <x-card :title="__('Recent online payments')" icon="bi-clock-history" body-class="p-0">
        @if ($payments->isEmpty())
            <x-empty-state :title="__('No online payments yet')" icon="bi-credit-card" />
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th class="ps-3">{{ __('When') }}</th><th>{{ __('Gateway') }}</th><th>{{ __('Purpose') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th class="pe-3">{{ __('Note') }}</th></tr></thead>
                    <tbody>
                        @foreach ($payments as $attempt)
                            <tr data-online-payment-row="{{ $attempt->uuid }}">
                                <td class="ps-3 text-nowrap">{{ $attempt->created_at?->inPropertyTime()->format('d M Y H:i') }}</td>
                                <td>{{ $attempt->provider->shortLabel() }}</td>
                                <td>{{ $attempt->purpose->label() }}</td>
                                <td class="text-end">{{ $attempt->currency_code }} {{ number_format((float) $attempt->amount, 2) }}</td>
                                <td><x-status-badge :status="$attempt->status" /></td>
                                <td class="pe-3 small text-body-secondary">{{ $attempt->failure_reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts::app>
