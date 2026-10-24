{{-- The test gateway's "hosted page" (Step 8.3; local and staging only): choose what the gateway does. --}}
<x-layouts::guest :title="__('Test payment')">
    <div class="card" data-test-gateway>
        <div class="card-body">
            <p class="login-box-msg mb-2"><span class="badge text-bg-warning">{{ __('Test gateway: no real money moves') }}</span></p>
            <p class="text-center fs-4 fw-semibold mb-3">{{ $attempt->currency_code }} {{ number_format((float) $attempt->amount, 2) }}</p>
            <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('billing.online.test.submit', ['uuid' => $attempt->uuid], now()->addHour()) }}" class="d-grid gap-2">
                @csrf
                <button name="outcome" value="paid" class="btn btn-success" data-test-pay>{{ __('Pay') }}</button>
                <button name="outcome" value="failed" class="btn btn-outline-danger" data-test-fail>{{ __('Fail the payment') }}</button>
                <button name="outcome" value="cancelled" class="btn btn-outline-secondary" data-test-cancel>{{ __('Cancel') }}</button>
            </form>
        </div>
    </div>
</x-layouts::guest>
