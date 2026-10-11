<x-layouts::app :title="$approval->reference" :subtitle="$typeLabel" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Approvals') => route('core.approvals.index'), $approval->reference => null]">
    <x-slot:actions>
        @if ($canCancel)
            <form method="POST" action="{{ route('core.approvals.cancel', $approval) }}" class="d-inline" data-confirm="{{ __('Withdraw this request?') }}" data-confirm-variant="danger">@csrf
                <button type="submit" class="btn btn-outline-danger" data-cancel><i class="bi bi-x-circle"></i> {{ __('Withdraw') }}</button></form>
        @endif
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Request')" icon="bi-file-earmark-check">
                <dl class="row mb-0" data-approval-summary>
                    <dt class="col-4">{{ __('Type') }}</dt><dd class="col-8">{{ $typeLabel }}</dd>
                    <dt class="col-4">{{ __('Reference') }}</dt><dd class="col-8">{{ $approval->reference }}</dd>
                    <dt class="col-4">{{ __('Amount') }}</dt><dd class="col-8 font-monospace">{{ number_format((float) $approval->amount, 2) }}</dd>
                    <dt class="col-4">{{ __('Asked by') }}</dt><dd class="col-8">{{ $requester }} · {{ $approval->created_at->inPropertyTime()->format('d M Y H:i') }}</dd>
                    <dt class="col-4">{{ __('Status') }}</dt><dd class="col-8"><x-status-badge :status="$approval->status" /></dd>
                </dl>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-approval-panel :steps="$steps" :approve-url="$canDecide ? route('core.approvals.approve', $approval) : null" :reject-url="$canDecide ? route('core.approvals.reject', $approval) : null" />
        </div>
    </div>
</x-layouts::app>
