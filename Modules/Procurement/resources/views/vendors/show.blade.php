<x-layouts::app :title="$vendor->name" :subtitle="$vendor->code" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Procurement') => null, __('Vendors') => route('procurement.vendors.index'), $vendor->name => null]">
    <x-slot:actions>
        @if ($canPay)<a href="{{ route('procurement.payments.create', ['vendor' => $vendor->id]) }}" class="btn btn-success" data-pay-vendor><i class="bi bi-cash-coin"></i> {{ __('Pay') }}</a>@endif
        @if ($canManage)<a href="{{ route('procurement.vendors.edit', $vendor) }}" class="btn btn-outline-secondary" data-edit-vendor><i class="bi bi-pencil"></i> {{ __('Change') }}</a>@endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Price list')" icon="bi-tag" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-prices>
                    <thead><tr><th class="ps-3">{{ __('Item') }}</th><th class="text-end">{{ __('Price') }}</th><th class="text-end">{{ __('From quantity') }}</th><th>{{ __('Valid') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($vendor->prices as $entry)
                            <tr data-price="{{ $items[$entry->item_id]->sku ?? $entry->item_id }}">
                                <td class="ps-3">{{ $items[$entry->item_id]->name ?? '#'.$entry->item_id }} <span class="small text-body-secondary">{{ $items[$entry->item_id]->sku ?? '' }}@if ($entry->vendor_sku) · {{ $entry->vendor_sku }}@endif</span></td>
                                <td class="text-end font-monospace">{{ number_format((float) $entry->price, 4) }}</td>
                                <td class="text-end font-monospace">{{ rtrim(rtrim((string) $entry->min_quantity, '0'), '.') ?: '0' }}</td>
                                <td>{{ $entry->valid_from?->format('d M Y') ?? '…' }} – {{ $entry->valid_to?->format('d M Y') ?? '…' }}
                                    @if ($entry->valid_to && $entry->valid_to->toDateString() < $today)<span class="badge text-bg-secondary">{{ __('expired') }}</span>@endif</td>
                                @if ($canManage)<td class="text-end pe-3"><form method="POST" action="{{ route('procurement.vendors.prices.destroy', [$vendor, $entry]) }}" data-confirm="{{ __('Remove this price?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></form></td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state :title="__('No prices listed')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($canManage)
                    <form method="POST" action="{{ route('procurement.vendors.prices.store', $vendor) }}" class="row g-2 p-3 border-top" data-price-form>
                        @csrf
                        <div class="col-md-4"><label class="form-label small" for="price-item">{{ __('Item') }}</label><select class="form-select form-select-sm" id="price-item" name="item_id" required><option value="">{{ __('Choose an item') }}</option>@foreach ($itemOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-2"><label class="form-label small" for="price-price">{{ __('Price') }}</label><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" id="price-price" name="price" required></div>
                        <div class="col-md-2"><label class="form-label small" for="price-min">{{ __('From quantity') }}</label><input type="number" step="0.0001" min="0" class="form-control form-control-sm text-end" id="price-min" name="min_quantity"></div>
                        <div class="col-md-2"><label class="form-label small" for="price-from">{{ __('Valid from') }}</label><input type="date" class="form-control form-control-sm" id="price-from" name="valid_from"></div>
                        <div class="col-md-2"><label class="form-label small" for="price-to">{{ __('Valid to') }}</label><input type="date" class="form-control form-control-sm" id="price-to" name="valid_to"></div>
                        <div class="col-12"><button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> {{ __('Add price') }}</button></div>
                    </form>
                @endif
            </x-card>
            <x-card :title="__('Recent orders')" icon="bi-cart3" body-class="p-0">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        @forelse ($orders as $order)
                            <tr><td class="ps-3"><a href="{{ route('procurement.orders.show', $order) }}">{{ $order->order_no }}</a></td><td>{{ $order->ordered_on->format('d M Y') }}</td><td class="text-end font-monospace">{{ $order->currency }} {{ number_format((float) $order->total, 2) }}</td><td class="pe-3"><x-status-badge :status="$order->status" /></td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('No orders yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Vendor')" icon="bi-shop">
                <dl class="row mb-0" data-vendor-summary>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$vendor->status" /></dd>
                    <dt class="col-5">{{ __('Rating') }}</dt><dd class="col-7">{{ $vendor->rating ? str_repeat('★', $vendor->rating) : '—' }}</dd>
                    @if ($vendor->tax_id)<dt class="col-5">{{ __('Tax ID') }}</dt><dd class="col-7">{{ $vendor->tax_id }}</dd>@endif
                    @if ($vendor->email)<dt class="col-5">{{ __('Email') }}</dt><dd class="col-7">{{ $vendor->email }}</dd>@endif
                    @if ($vendor->phone)<dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7">{{ $vendor->phone }}</dd>@endif
                    @if ($vendor->address)<dt class="col-5">{{ __('Address') }}</dt><dd class="col-7">{{ $vendor->address }}</dd>@endif
                    <dt class="col-5">{{ __('Payable') }}</dt><dd class="col-7 font-monospace" data-payable>{{ number_format((float) $payable, 2) }}</dd>
                    <dt class="col-5">{{ __('Advances') }}</dt><dd class="col-7 font-monospace" data-advances>{{ number_format((float) $advance, 2) }}</dd>
                    <dt class="col-5">{{ __('Currency') }}</dt><dd class="col-7">{{ $vendor->currency }}</dd>
                    <dt class="col-5">{{ __('Terms') }}</dt><dd class="col-7">{{ trans_choice(':count day|:count days', $vendor->payment_terms_days) }}</dd>
                    @if ($vendor->bank_name || $vendor->bank_account_no)<dt class="col-5">{{ __('Bank') }}</dt><dd class="col-7">{{ collect([$vendor->bank_name, $vendor->bank_branch, $vendor->bank_account_name, $vendor->bank_account_no])->filter()->implode(' · ') }}</dd>@endif
                    @if ($vendor->notes)<dt class="col-5">{{ __('Notes') }}</dt><dd class="col-7">{{ $vendor->notes }}</dd>@endif
                </dl>
            </x-card>
            <x-card :title="__('Contacts')" icon="bi-person-lines-fill" body-class="p-0">
                <ul class="list-group list-group-flush" data-contacts>
                    @forelse ($vendor->contacts as $contact)
                        <li class="list-group-item d-flex justify-content-between align-items-start">
                            <div>{{ $contact->name }}@if ($contact->is_primary) <span class="badge text-bg-primary">{{ __('primary') }}</span>@endif<div class="small text-body-secondary">{{ collect([$contact->role, $contact->email, $contact->phone])->filter()->implode(' · ') }}</div></div>
                            @if ($canManage)<form method="POST" action="{{ route('procurement.vendors.contacts.destroy', [$vendor, $contact]) }}" data-confirm="{{ __('Remove this contact?') }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button></form>@endif
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No contacts yet') }}</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('procurement.vendors.contacts.store', $vendor) }}" class="p-3 border-top row g-2" data-contact-form>
                        @csrf
                        <div class="col-6"><input class="form-control form-control-sm" name="name" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}" required></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="role" placeholder="{{ __('Role') }}" aria-label="{{ __('Role') }}"></div>
                        <div class="col-6"><input type="email" class="form-control form-control-sm" name="email" placeholder="{{ __('Email') }}" aria-label="{{ __('Email') }}"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="phone" placeholder="{{ __('Phone') }}" aria-label="{{ __('Phone') }}"></div>
                        <div class="col-12 d-flex justify-content-between"><div class="form-check"><input type="hidden" name="is_primary" value="0"><input class="form-check-input" type="checkbox" id="contact-primary" name="is_primary" value="1"><label class="form-check-label small" for="contact-primary">{{ __('Primary contact') }}</label></div><button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Add contact') }}</button></div>
                    </form>
                @endif
            </x-card>
            <x-attachments :subject="$vendor" :title="__('Documents')" />
            <x-audit-trail :entries="$trail" />
        </div>
    </div>
</x-layouts::app>
