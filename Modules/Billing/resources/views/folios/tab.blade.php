@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $canPost = auth()->user()?->can('billing.folio.post');
    $canAdjust = auth()->user()?->can('billing.folio.adjust');
    $canVoid = auth()->user()?->can('billing.folio.void');
@endphp

@if ($errors->folio->any())
    <div class="alert alert-danger" data-folio-error>{{ $errors->folio->first() }}</div>
@endif

@foreach ($folios as $folio)
    <x-card body-class="p-0" data-folio="{{ $folio->folio_no }}">
        <x-slot:title>
            <span class="font-monospace">{{ $folio->folio_no }}</span>
            <x-status-badge :status="$folio->type" />
            <span class="text-body-secondary small">{{ $folio->name }}</span>
            @if (! $folio->isOpen())<x-status-badge :status="$folio->status" />@endif
        </x-slot:title>
        <div class="d-flex flex-wrap gap-2 align-items-center px-3 py-2 border-bottom">
            <span class="me-auto">{{ __('Balance') }}: <strong class="font-monospace" data-folio-balance>{{ $folio->currency_code }} {{ $money($folio->balance) }}</strong></span>
            @if ($folio->isOpen())
                @if ($canPost)
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#post-charge-{{ $folio->id }}"><i class="bi bi-plus-lg"></i> {{ __('Post charge') }}</button>
                @endif
                @if ($canAdjust)
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#adjust-{{ $folio->id }}"><i class="bi bi-plus-slash-minus"></i> {{ __('Adjustment') }}</button>
                @endif
                @if ($canPost && bccomp($folio->balance, "0", 2) > 0 && $companies !== [])
                    <form method="POST" action="{{ route('billing.folios.transfer', $folio) }}" class="d-flex gap-1" data-transfer="{{ $folio->folio_no }}"
                        data-confirm="{{ __('Move this balance to the company\'s account?') }}">
                        @csrf
                        <select name="company_id" class="form-select form-select-sm w-auto" aria-label="{{ __('Company') }}">
                            @foreach ($companies as $company)<option value="{{ $company['id'] }}" @selected($folio->bill_to_type === \Modules\Billing\Enums\BillTo::Company && $folio->bill_to_id === $company['id'])>{{ $company['name'] }}</option>@endforeach
                        </select>
                        <button class="btn btn-sm btn-outline-info"><i class="bi bi-building"></i> {{ __('To city ledger') }}</button>
                    </form>
                @endif
                @if ($canVoid && $folio->lines->contains(fn ($line) => ! $line->is_voided && in_array($line->line_type, [\Modules\Billing\Enums\FolioLineType::Charge, \Modules\Billing\Enums\FolioLineType::Adjustment], true)))
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#void-{{ $folio->id }}"><i class="bi bi-slash-circle"></i> {{ __('Void a line') }}</button>
                @endif
            @endif
        </div>
        @if ($folio->lines->isEmpty())
            <div class="p-3 text-body-secondary small">{{ __('Nothing posted yet.') }}</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm mb-0" data-folio-lines>
                    <thead><tr><th class="ps-3">{{ __('Date') }}</th><th>{{ __('Description') }}</th><th>{{ __('Code') }}</th><th class="text-end">{{ __('Qty') }}</th><th class="text-end">{{ __('Amount') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                    <tbody>
                        @foreach ($folio->lines as $line)
                            <tr @class(['text-body-tertiary' => $line->is_voided]) data-line="{{ $line->id }}">
                                <td class="ps-3 text-nowrap">{{ $line->posting_date->format('d M') }}</td>
                                <td>
                                    <span @class(['text-decoration-line-through' => $line->is_voided])>{{ $line->description }}</span>
                                    @if ($line->line_type !== \Modules\Billing\Enums\FolioLineType::Charge)<x-status-badge :status="$line->line_type" />@endif
                                    @if ($line->routed_from_folio_id)<span class="badge text-bg-light border" title="{{ __('Routed by a rule') }}"><i class="bi bi-signpost-split"></i></span>@endif
                                    @if ($line->is_voided)<div class="small">{{ __('Void: :reason', ['reason' => $line->void_reason]) }} · {{ $userNames[$line->voided_by] ?? '' }}</div>@endif
                                </td>
                                <td class="font-monospace small">{{ $line->chargeCode?->code }}</td>
                                <td class="text-end">{{ rtrim(rtrim($line->quantity, '0'), '.') }}</td>
                                <td class="text-end font-monospace">{{ $money($line->amount) }}</td>
                                <td class="text-end font-monospace">{{ $money($line->tax_amount) }}</td>
                                <td class="text-end pe-3 font-monospace">{{ $line->line_type === \Modules\Billing\Enums\FolioLineType::Payment ? '−' : '' }}{{ $money($line->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($folio->isOpen() && $canPost)
        <x-modal :id="'post-charge-'.$folio->id" :title="__('Post a charge to :no', ['no' => $folio->folio_no])">
            <form method="POST" action="{{ route('billing.folios.charge', $folio) }}" id="post-charge-form-{{ $folio->id }}" data-post-charge>
                @csrf
                <x-form.select name="extra_service_id" :id="'extra-'.$folio->id" :label="__('Extra')" :placeholder="__('— or choose a charge code below —')" :search="false"
                    :options="$extras->mapWithKeys(fn ($extra) => [$extra->id => $extra->name.' · '.$money($extra->unit_price).($extra->price_includes_tax ? ' '.__('incl. tax') : '')])->all()" />
                <x-form.select name="charge_code_id" :id="'code-'.$folio->id" :label="__('Charge code')" :placeholder="__('— the extra\'s own —')" :search="false"
                    :options="$codes->mapWithKeys(fn ($code) => [$code->id => $code->code.' — '.$code->name])->all()" />
                <div class="row">
                    <div class="col-6"><x-form.input name="unit_price" :id="'price-'.$folio->id" type="number" step="0.01" min="0" :label="__('Price')" :help="__('Leave empty to use the extra\'s price.')" /></div>
                    <div class="col-6"><x-form.input name="quantity" :id="'qty-'.$folio->id" type="number" min="1" max="999" :label="__('Quantity')" :value="1" required /></div>
                </div>
                <x-form.input name="description" :id="'desc-'.$folio->id" :label="__('Description')" maxlength="190" />
                <input type="hidden" name="price_includes_tax" value="0">
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="submit" form="post-charge-form-{{ $folio->id }}" class="btn btn-primary">{{ __('Post charge') }}</button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($folio->isOpen() && $canAdjust)
        <x-modal :id="'adjust-'.$folio->id" :title="__('Adjust :no', ['no' => $folio->folio_no])">
            <form method="POST" action="{{ route('billing.folios.adjust', $folio) }}" id="adjust-form-{{ $folio->id }}" data-post-adjustment>
                @csrf
                <x-form.input name="amount" :id="'adj-amount-'.$folio->id" type="number" step="0.01" :label="__('Amount (tax included)')" required :help="__('Positive adds to the bill; negative is a credit.')" />
                <x-form.input name="reason" :id="'adj-reason-'.$folio->id" :label="__('Reason')" required maxlength="190" />
                <x-form.select name="charge_code_id" :id="'adj-code-'.$folio->id" :label="__('Charge code')" :placeholder="__('None')" :search="false"
                    :options="$codes->mapWithKeys(fn ($code) => [$code->id => $code->code.' — '.$code->name])->all()" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="submit" form="adjust-form-{{ $folio->id }}" class="btn btn-primary">{{ __('Post adjustment') }}</button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($folio->isOpen() && $canVoid)
        <x-modal :id="'void-'.$folio->id" :title="__('Void a line of :no', ['no' => $folio->folio_no])">
            <form method="POST" action="{{ route('billing.folios.void', $folio) }}" id="void-form-{{ $folio->id }}" data-void-line>
                @csrf
                <x-form.select name="folio_line_id" :id="'void-line-'.$folio->id" :label="__('Line')" required :search="false"
                    :options="$folio->lines->filter(fn ($line) => ! $line->is_voided && in_array($line->line_type, [\Modules\Billing\Enums\FolioLineType::Charge, \Modules\Billing\Enums\FolioLineType::Adjustment], true))
                        ->mapWithKeys(fn ($line) => [$line->id => $line->posting_date->format('d M').' · '.$line->description.' · '.$money($line->total)])->all()" />
                <x-form.input name="reason" :id="'void-reason-'.$folio->id" :label="__('Reason')" required maxlength="190" />
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="submit" form="void-form-{{ $folio->id }}" class="btn btn-danger">{{ __('Void line') }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
@endforeach

@if ($canPost)
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Routing')" icon="bi-signpost-split" body-class="p-0">
                <p class="small text-body-secondary px-3 pt-3 mb-2">{{ __('Where new charges of each kind go, e.g. room to the company folio and food to the guest.') }}</p>
                <table class="table table-sm mb-0 align-middle" data-routing>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="ps-3"><x-status-badge :status="$category" /></td>
                                <td class="pe-3">
                                    <form method="POST" action="{{ route('billing.folios.route') }}" class="d-flex gap-2" data-route="{{ $category->value }}">
                                        @csrf
                                        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                                        <input type="hidden" name="category" value="{{ $category->value }}">
                                        <select name="target_folio_id" class="form-select form-select-sm" aria-label="{{ __('Folio') }}">
                                            @foreach ($folios as $folio)
                                                <option value="{{ $folio->id }}" @selected(($routes[$category->value]->target_folio_id ?? $folios->firstWhere('type', \Modules\Billing\Enums\FolioType::Guest)?->id) === $folio->id)>{{ $folio->folio_no }} · {{ $folio->name }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Another folio')" icon="bi-folder-plus">
                <form method="POST" action="{{ route('billing.folios.store') }}" data-open-folio x-data="{ billTo: 'company' }">
                    @csrf
                    <input type="hidden" name="reservation_id" value="{{ $reservation->id }}">
                    <x-form.select name="type" :label="__('Folio')" :search="false" required :options="collect($folioTypes)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all()" :value="'company'" />
                    <x-form.select name="bill_to_type" :label="__('Who pays')" :search="false" required x-model="billTo"
                        :options="collect($billTo)->mapWithKeys(fn ($option) => [$option->value => $option->label()])->all()" :value="'company'" />
                    <div x-show="billTo === 'company'"><x-form.select name="company_id" :label="__('Company')" :options="collect($companies)->pluck('name', 'id')->all()" /></div>
                    <div x-show="billTo === 'travel_agent'" x-cloak><x-form.select name="travel_agent_id" :label="__('Travel agent')" :options="collect($agents)->pluck('name', 'id')->all()" /></div>
                    <button class="btn btn-outline-primary w-100"><i class="bi bi-folder-plus"></i> {{ __('Open folio') }}</button>
                </form>
            </x-card>
        </div>
    </div>
@endif

@if ($invoices->isNotEmpty())
    <x-card :title="__('Invoices')" icon="bi-file-earmark-text" body-class="p-0">
        <table class="table align-middle mb-0" data-invoices>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr data-invoice="{{ $invoice->invoice_no }}">
                        <td class="ps-3">
                            <span class="font-monospace fw-semibold">{{ $invoice->invoice_no }}</span> <x-status-badge :status="$invoice->status" />
                            <div class="small text-body-secondary">{{ $invoice->bill_to_name }} · {{ $invoice->issue_date->format('d M Y') }}</div>
                            @foreach ($invoice->creditNotes as $note)
                                <div class="small">{{ __('Credit note :no: :amount — :reason', ['no' => $note->credit_note_no, 'amount' => $money($note->amount), 'reason' => $note->reason]) }}
                                    <a href="{{ route('billing.credit-notes.pdf', $note) }}">PDF</a></div>
                            @endforeach
                        </td>
                        <td class="text-end font-monospace">{{ $invoice->currency_code }} {{ $money($invoice->total) }}</td>
                        <td class="text-end pe-3 text-nowrap">
                            <a href="{{ route('billing.invoices.pdf', $invoice) }}" class="btn btn-sm btn-outline-secondary" data-invoice-pdf><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                            @can('credit', $invoice)
                                @if ($invoice->status !== \Modules\Billing\Enums\InvoiceStatus::Credited)
                                    <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#credit-{{ $invoice->id }}">{{ __('Credit note') }}</button>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
    @foreach ($invoices as $invoice)
        @can('credit', $invoice)
            <x-modal :id="'credit-'.$invoice->id" :title="__('Credit note for :no', ['no' => $invoice->invoice_no])">
                <form method="POST" action="{{ route('billing.invoices.credit', $invoice) }}" id="credit-form-{{ $invoice->id }}" data-credit-note>
                    @csrf
                    <x-form.input name="amount" :id="'credit-amount-'.$invoice->id" type="number" step="0.01" min="0.01" :label="__('Amount')" required
                        :help="__('At most :max. It first reduces what the company still owes; the rest is refundable.', ['max' => $money(bcsub($invoice->total, $invoice->credited, 2))])" />
                    <x-form.input name="reason" :id="'credit-reason-'.$invoice->id" :label="__('Reason')" required maxlength="190" />
                </form>
                <x-slot:footer>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="submit" form="credit-form-{{ $invoice->id }}" class="btn btn-warning">{{ __('Issue credit note') }}</button>
                </x-slot:footer>
            </x-modal>
        @endcan
    @endforeach
@endif
