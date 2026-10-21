<x-layouts::platform :title="__('Invoice :number', ['number' => $invoice->number])" :subtitle="$invoice->tenant->name"
    :breadcrumbs="[__('Platform') => route('platform.console'), __('Invoices') => route('platform.invoices.index'), $invoice->number => null]">
    <x-slot:actions>
        <a href="{{ route('platform.invoices.pdf', $invoice) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i> {{ __('PDF') }}</a>
    </x-slot:actions>
    <div class="row">
        <div class="col-lg-6">
            <x-card :title="__('Invoice')" icon="bi-receipt">
                <dl class="row mb-0" data-invoice>
                    <dt class="col-sm-4">{{ __('Tenant') }}</dt><dd class="col-sm-8"><a href="{{ route('platform.tenants.show', $invoice->tenant_id) }}">{{ $invoice->tenant->name }}</a></dd>
                    <dt class="col-sm-4">{{ __('For') }}</dt><dd class="col-sm-8">{{ $invoice->description }} · {{ $invoice->period_start?->format('d M Y') }} – {{ $invoice->period_end?->format('d M Y') }}</dd>
                    <dt class="col-sm-4">{{ __('Issued / due') }}</dt><dd class="col-sm-8">{{ $invoice->issued_on->format('d M Y') }} / <span @class(['text-danger fw-semibold' => $invoice->isOverdue()])>{{ $invoice->due_on->format('d M Y') }}</span></dd>
                    <dt class="col-sm-4">{{ __('Amount') }}</dt><dd class="col-sm-8">{{ $invoice->currency_code }} {{ $invoice->amount }}</dd>
                    <dt class="col-sm-4">{{ __('Balance') }}</dt><dd class="col-sm-8">{{ $invoice->currency_code }} {{ $invoice->balance() }}</dd>
                    <dt class="col-sm-4">{{ __('Status') }}</dt><dd class="col-sm-8"><x-status-badge :status="$invoice->status" />@if ($invoice->void_reason) <span class="small text-body-secondary">{{ $invoice->void_reason }}</span>@endif</dd>
                </dl>
            </x-card>

            <x-card :title="__('Payments')" icon="bi-cash-coin" body-class="p-0">
                <table class="table table-sm mb-0" data-payments>
                    <tbody>
                        @forelse ($invoice->payments as $payment)
                            <tr><td class="ps-3">{{ $payment->paid_on->format('d M Y') }}</td><td>{{ $payment->method->label() }} @if ($payment->reference)<span class="small text-body-secondary">{{ $payment->reference }}</span>@endif</td><td class="text-end pe-3">{{ $payment->amount }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('None yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        @if ($invoice->status === \Modules\Platform\Enums\PlatformInvoiceStatus::Open)
            <div class="col-lg-6">
                <form method="POST" action="{{ route('platform.invoices.pay', $invoice) }}" data-payment-form>
                    @csrf
                    <x-card :title="__('Record a payment')" icon="bi-plus-circle">
                        <div class="row">
                            <div class="col-md-6"><x-form.money name="amount" :label="__('Amount')" :currency="$invoice->currency_code" :value="$invoice->balance()" required /></div>
                            <div class="col-md-6"><x-form.date name="paid_on" :label="__('Paid on')" :value="today()->toDateString()" required /></div>
                            <div class="col-md-6"><x-form.select name="method" :label="__('Method')" :options="$methods" value="bank_transfer" :search="false" required /></div>
                            <div class="col-md-6"><x-form.input name="reference" :label="__('Reference')" /></div>
                        </div>
                        <button type="submit" class="btn btn-primary">{{ __('Record the payment') }}</button>
                    </x-card>
                </form>

                @if (\Brick\Math\BigDecimal::of($invoice->paid_amount)->isZero())
                    <form method="POST" action="{{ route('platform.invoices.void', $invoice) }}" class="d-flex gap-2" data-void-form data-confirm="{{ __('Void invoice :number?', ['number' => $invoice->number]) }}" data-confirm-variant="danger">
                        @csrf
                        <input type="text" name="reason" class="form-control" placeholder="{{ __('Why it is voided') }}" aria-label="{{ __('Why it is voided') }}" required>
                        <button type="submit" class="btn btn-outline-danger text-nowrap">{{ __('Void') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-layouts::platform>
