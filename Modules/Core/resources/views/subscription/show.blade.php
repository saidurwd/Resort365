<x-layouts::app :title="__('Subscription')" :breadcrumbs="[__('Setup') => null, __('Subscription') => null]">
    <div class="row">
        <div class="col-xl-6">
            <x-card :title="__('Your plan')" icon="bi-award">
                @if ($subscription === null)
                    <p class="mb-0 text-body-secondary">{{ __('Your company has no subscription with the platform; nothing is billed.') }}</p>
                @else
                    <dl class="row mb-0" data-subscription>
                        <dt class="col-sm-4">{{ __('Plan') }}</dt>
                        <dd class="col-sm-8">{{ $subscription->planName }} <span class="badge text-bg-{{ $subscription->statusColor }}">{{ $subscription->statusLabel }}</span></dd>
                        <dt class="col-sm-4">{{ __('Price') }}</dt>
                        <dd class="col-sm-8">{{ $subscription->currencyCode }} {{ $subscription->price }} · {{ $subscription->billingPeriod }}</dd>
                        @if ($subscription->endsAt)
                            <dt class="col-sm-4">{{ $subscription->trialing() ? __('Trial ends') : __('Paid until') }}</dt>
                            <dd class="col-sm-8">{{ $subscription->endsAt->subDay()->format('d M Y') }}</dd>
                        @endif
                    </dl>

                    @if ($subscription->trialing() && ! $subscription->cancelAtPeriodEnd)
                        <p class="small text-body-secondary mt-3 mb-0">{{ __('When the trial ends, your subscription starts and you get the first invoice, payable by bank transfer.') }}</p>
                    @endif

                    @if ($subscription->hasOverdue())
                        <div class="alert alert-danger mt-3 mb-0">{{ __(':currency :amount is overdue. Please pay it to keep using the system.', ['currency' => $subscription->currencyCode, 'amount' => $subscription->overdue]) }}</div>
                    @endif

                    @if ($subscription->cancelAtPeriodEnd)
                        <div class="alert alert-warning mt-3 mb-0 d-flex justify-content-between align-items-center gap-2">
                            <span>{{ __('Cancelled: your company closes on :date.', ['date' => $subscription->endsAt?->format('d M Y')]) }}</span>
                            @can('core.subscription.manage')
                                <form method="POST" action="{{ route('core.subscription.resume') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning">{{ __('Keep my subscription') }}</button>
                                </form>
                            @endcan
                        </div>
                    @endif
                @endif
            </x-card>

            <x-card :title="__('Usage')" icon="bi-speedometer" body-class="p-0">
                <table class="table mb-0">
                    <thead><tr><th class="ps-3">{{ __('What') }}</th><th class="text-end">{{ __('Used') }}</th><th class="text-end pe-3">{{ __('Plan allows') }}</th></tr></thead>
                    <tbody>
                        @foreach ($usage as $row)
                            <tr>
                                <td class="ps-3">{{ $row['label'] }}</td>
                                <td class="text-end">{{ $row['used'] }} @if ($row['unit'] !== 'count'){{ $row['unit'] }}@endif</td>
                                <td class="text-end pe-3">{{ $row['limit'] ?? __('No limit') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>

        <div class="col-xl-6">
            <x-card :title="__('Invoices')" icon="bi-receipt" body-class="p-0">
                @if ($invoices === [])
                    <x-empty-state icon="bi-receipt" :title="__('No invoices yet')" :message="__('Your first invoice comes when the trial ends.')" />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-platform-invoices>
                            <thead><tr><th class="ps-3">{{ __('Number') }}</th><th>{{ __('Issued') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th class="pe-3"></th></tr></thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td class="ps-3">{{ $invoice->number }}</td>
                                        <td>{{ $invoice->issuedOn->format('d M Y') }}</td>
                                        <td @class(['text-danger fw-semibold' => $invoice->overdue])>{{ $invoice->dueOn->format('d M Y') }}</td>
                                        <td class="text-end">{{ $invoice->currencyCode }} {{ $invoice->amount }}</td>
                                        <td><span class="badge text-bg-{{ $invoice->statusColor }}">{{ $invoice->statusLabel }}</span></td>
                                        <td class="pe-3 text-end"><a href="{{ route('core.subscription.invoice', $invoice->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="bi bi-filetype-pdf"></i> {{ __('PDF') }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            @can('core.data-export.manage')
                <div id="data">
                    <x-card :title="__('Your data')" icon="bi-file-earmark-zip">
                        <p class="text-body-secondary">{{ __('Download everything your company has entered: a ZIP with a spreadsheet (CSV) per table and your attachments. It is ready in a few minutes and kept for a week.') }}</p>
                        <form method="POST" action="{{ route('core.data-exports.store') }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-download"></i> {{ __('Export all data') }}</button>
                        </form>
                        @error('export')<div class="alert alert-danger">{{ $message }}</div>@enderror
                        @foreach ($exports as $export)
                            <div class="d-flex justify-content-between align-items-center border-top py-2 gap-2">
                                <span class="small">{{ $export->requestedAt->inPropertyTime()->format('d M Y H:i') }} · {{ $export->requestedBy }}
                                    <span class="badge text-bg-{{ $export->statusColor }}">{{ $export->statusLabel }}</span></span>
                                @if ($export->ready())
                                    <a href="{{ route('core.data-exports.download', $export->id) }}" class="btn btn-sm btn-primary">{{ __('Download') }}</a>
                                @endif
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endcan

            @if ($subscription !== null && ! $subscription->cancelAtPeriodEnd)
                @can('core.subscription.manage')
                    <x-card :title="__('Cancel the subscription')" icon="bi-x-octagon">
                        <p class="text-body-secondary">{{ __('You keep working until the end of the trial or the paid period. After that the system closes and your data is kept for :days days; export it first.', ['days' => config('platform.billing.retention_days', 30)]) }}</p>
                        <form method="POST" action="{{ route('core.subscription.cancel') }}" data-confirm="{{ __('Cancel the subscription?') }}" data-confirm-variant="danger">
                            @csrf
                            <x-form.input name="reason" :label="__('Why are you leaving?')" required />
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="confirm" id="field-confirm" value="1" required>
                                <label class="form-check-label" for="field-confirm">{{ __('I understand that the system closes for everyone in my company.') }}</label>
                            </div>
                            <button type="submit" class="btn btn-outline-danger">{{ __('Cancel the subscription') }}</button>
                        </form>
                    </x-card>
                @endcan
            @endif
        </div>
    </div>
</x-layouts::app>
