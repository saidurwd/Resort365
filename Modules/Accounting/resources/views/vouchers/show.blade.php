@php($posted = $voucher->status === \Modules\Accounting\Enums\VoucherStatus::Posted)
<x-layouts::app :title="$voucher->voucher_no" :subtitle="$voucher->description" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Vouchers') => route('accounting.vouchers.index'), $voucher->voucher_no => null]">
    <x-slot:actions>
        @if ($posted)
            @if ($voucher->cheque_status === \Modules\Accounting\Enums\ChequeStatus::Pending && auth()->user()?->can('accounting.bank.manage'))
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#bounce-modal" data-bounce><i class="bi bi-slash-circle"></i> {{ __('Cheque bounced') }}</button>
            @endif
            @can('void', $voucher)
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#void-modal" data-void><i class="bi bi-x-circle"></i> {{ __('Void') }}</button>
            @endcan
        @endif
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Voucher')" icon="bi-receipt">
                <dl class="row mb-0" data-voucher-summary>
                    <dt class="col-5">{{ __('Number') }}</dt><dd class="col-7">{{ $voucher->voucher_no }}</dd>
                    <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7"><x-status-badge :status="$voucher->type" /> <x-status-badge :status="$voucher->status" /></dd>
                    <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $voucher->voucher_date->format('d M Y') }}</dd>
                    <dt class="col-5">{{ $voucher->type === \Modules\Accounting\Enums\VoucherType::Income ? __('Income account') : __('Expense account') }}</dt><dd class="col-7">{{ $voucher->account->label() }}</dd>
                    <dt class="col-5">{{ $voucher->type === \Modules\Accounting\Enums\VoucherType::Income ? __('Received into') : __('Paid from') }}</dt><dd class="col-7">{{ $voucher->cashAccount->label() }}</dd>
                    <dt class="col-5">{{ __('Amount') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $voucher->amount, 2) }}</dd>
                    @if ((float) $voucher->tax_amount > 0)<dt class="col-5">{{ __('Input VAT') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $voucher->tax_amount, 2) }}</dd>@endif
                    @if ($voucher->payee)<dt class="col-5">{{ __('Payee') }}</dt><dd class="col-7">{{ $voucher->payee }}</dd>@endif
                    @if ($department)<dt class="col-5">{{ __('Department') }}</dt><dd class="col-7">{{ $department }}</dd>@endif
                    @if ($voucher->cheque_no)<dt class="col-5">{{ __('Cheque') }}</dt><dd class="col-7" data-cheque>{{ $voucher->cheque_no }} @if ($voucher->cheque_status)<x-status-badge :status="$voucher->cheque_status" />@endif</dd>@endif
                    @if ($voucher->reference)<dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $voucher->reference }}</dd>@endif
                    @if ($voucher->entry)<dt class="col-5">{{ __('Journal entry') }}</dt><dd class="col-7"><a href="{{ route('accounting.journals.show', $voucher->entry) }}">{{ $voucher->entry->entry_no }}</a></dd>@endif
                    @if (! $posted)<dt class="col-5">{{ __('Voided') }}</dt><dd class="col-7">{{ $names[$voucher->voided_by] ?? '' }} · {{ $voucher->voided_at?->inPropertyTime()->format('d M Y H:i') }} · {{ $voucher->void_reason }}</dd>@endif
                </dl>
            </x-card>
            <x-attachments :subject="$voucher" :title="__('Receipts and invoices')" />
        </div>
        <div class="col-xl-5">
            <x-audit-trail :entries="$trail" />
        </div>
    </div>

    @if ($voucher->cheque_status === \Modules\Accounting\Enums\ChequeStatus::Pending && auth()->user()?->can('accounting.bank.manage'))
        <x-modal id="bounce-modal" :title="__('Cheque :no bounced', ['no' => $voucher->cheque_no])" :show="false">
            <form method="POST" action="{{ route('accounting.cheques.bounce', $voucher) }}" data-bounce-form>
                @csrf
                <p class="text-body-secondary">{{ __('The voucher is voided and its ledger entry reversed.') }}</p>
                <x-form.input name="reason" :label="__('Reason')" required />
                <button type="submit" class="btn btn-danger">{{ __('Mark bounced') }}</button>
            </form>
        </x-modal>
    @endif

    @can('void', $voucher)
        <x-modal id="void-modal" :title="__('Void :no', ['no' => $voucher->voucher_no])" :show="$errors->has('reason')">
            <form method="POST" action="{{ route('accounting.vouchers.void', $voucher) }}" data-void-form>
                @csrf
                <p class="text-body-secondary">{{ __('The ledger entry is reversed and the voucher marked void.') }}</p>
                <x-form.input name="reason" :label="__('Reason')" required />
                <button type="submit" class="btn btn-danger">{{ __('Void voucher') }}</button>
            </form>
        </x-modal>
    @endcan
</x-layouts::app>
