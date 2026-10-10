@php($posted = $transfer->status === \Modules\Accounting\Enums\VoucherStatus::Posted)
<x-layouts::app :title="$transfer->transfer_no" :subtitle="$transfer->from->name.' → '.$transfer->to->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Transfers') => route('accounting.transfers.index'), $transfer->transfer_no => null]">
    <x-slot:actions>
        @if ($posted && $canManage)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#void-modal" data-void><i class="bi bi-x-circle"></i> {{ __('Void') }}</button>
        @endif
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Transfer')" icon="bi-arrow-left-right">
                <dl class="row mb-0" data-transfer-summary>
                    <dt class="col-5">{{ __('Number') }}</dt><dd class="col-7">{{ $transfer->transfer_no }} <x-status-badge :status="$transfer->status" /></dd>
                    <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $transfer->transfer_date->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('From') }}</dt><dd class="col-7">{{ $transfer->from->name }}</dd>
                    <dt class="col-5">{{ __('To') }}</dt><dd class="col-7">{{ $transfer->to->name }}</dd>
                    <dt class="col-5">{{ __('Amount') }}</dt><dd class="col-7 font-monospace">{{ number_format((float) $transfer->amount, 2) }}</dd>
                    @if ($transfer->reference)<dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $transfer->reference }}</dd>@endif
                    @if ($transfer->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $transfer->notes }}</dd>@endif
                    @if ($transfer->entry)<dt class="col-5">{{ __('Journal entry') }}</dt><dd class="col-7"><a href="{{ route('accounting.journals.show', $transfer->entry) }}">{{ $transfer->entry->entry_no }}</a></dd>@endif
                    @if (! $posted)<dt class="col-5">{{ __('Voided') }}</dt><dd class="col-7">{{ $transfer->voided_at?->inPropertyTime()->format('d M Y H:i') }} · {{ $transfer->void_reason }}</dd>@endif
                </dl>
            </x-card>
        </div>
        <div class="col-xl-5"><x-audit-trail :entries="$trail" /></div>
    </div>

    @if ($canManage)
        <x-modal id="void-modal" :title="__('Void :no', ['no' => $transfer->transfer_no])" :show="$errors->has('reason')">
            <form method="POST" action="{{ route('accounting.transfers.void', $transfer) }}" data-void-form>
                @csrf
                <p class="text-body-secondary">{{ __('The ledger entry is reversed and the transfer marked void.') }}</p>
                <x-form.input name="reason" :label="__('Reason')" required />
                <button type="submit" class="btn btn-danger">{{ __('Void transfer') }}</button>
            </form>
        </x-modal>
    @endif
</x-layouts::app>
