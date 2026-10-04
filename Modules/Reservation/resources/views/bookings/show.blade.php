@php
    $money = fn (?string $amount): string => number_format((float) $amount, 2);
    $percent = fn (?string $value): string => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
    $changeable = $reservation->isChangeable();
    $canUpdate = $changeable && auth()->user()?->can('update', $reservation);
    $canCancel = $changeable && auth()->user()?->can('cancel', $reservation);
    $itemLabel = fn ($item): string => $item->room_id ? ($labels['room:'.$item->room_id] ?? '') : ($labels['cottage:'.$item->cottage_id] ?? '');
@endphp

<x-layouts::app :title="$reservation->code" :subtitle="$guest?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reservations') => route('reservation.bookings.index'), $reservation->code => null]">
    <x-slot:actions>
        @if ($canUpdate)
            <a href="{{ route('reservation.bookings.edit', $reservation) }}" class="btn btn-sm btn-outline-primary" data-action="modify"><i class="bi bi-pencil-square"></i> {{ __('Change stay') }}</a>
        @endif
        @if ($canCancel)
            <a href="{{ route('reservation.bookings.cancel', $reservation) }}" class="btn btn-sm btn-outline-danger" data-action="cancel"><i class="bi bi-x-circle"></i> {{ __('Cancel booking') }}</a>
        @endif
    </x-slot:actions>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-reservation-header>
        <x-status-badge :status="$reservation->status" />
        <x-status-badge :status="$reservation->payment_status" />
        @if ($reservation->group_name)<span class="badge text-bg-info" data-group><i class="bi bi-people"></i> {{ $reservation->group_name }}</span>@endif
        <span class="text-body-secondary">{{ $reservation->check_in->format('D d M Y') }} → {{ $reservation->check_out->format('D d M Y') }} · {{ trans_choice(':count night|:count nights', $reservation->nights()) }} · {{ $plan?->name }}</span>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist" data-hash-tabs>
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-summary" data-tab-hash="summary"><i class="bi bi-card-text"></i> {{ __('Summary') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-rooms" data-tab-hash="rooms"><i class="bi bi-houses"></i> {{ __('Rooms') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-guests" data-tab-hash="guests"><i class="bi bi-people"></i> {{ __('Guests') }} <span class="badge text-bg-secondary">{{ $guests->count() }}</span></button></li>
        @foreach ($tabs as $extra)
            <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-{{ $extra['tab']->key }}" data-tab-hash="{{ $extra['tab']->key }}"><i class="bi {{ $extra['tab']->icon }}"></i> {{ __($extra['tab']->label) }}</button></li>
        @endforeach
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-history" data-tab-hash="history"><i class="bi bi-clock-history"></i> {{ __('History') }}</button></li>
    </ul>

    <div class="tab-content">
        {{-- Summary --}}
        <div class="tab-pane fade show active" id="tab-summary" role="tabpanel">
            <div class="row">
                <div class="col-xl-7">
                    @if ($reservation->status === \Modules\Reservation\Enums\ReservationStatus::Cancelled)
                        <x-card :title="__('Cancelled')" icon="bi-x-circle" variant="danger" data-cancellation>
                            <p class="mb-1">{{ __('Cancelled on :time', ['time' => $reservation->cancelled_at?->setTimezone($timezone)->format('d M Y H:i')]) }}</p>
                            <p class="mb-1"><span class="text-body-secondary">{{ __('Reason:') }}</span> {{ $reservation->cancellation_reason }}</p>
                            <p class="mb-0"><span class="text-body-secondary">{{ __('Fee:') }}</span> {{ $money($reservation->cancellation_fee) }} · <span class="text-body-secondary">{{ __('Refund due:') }}</span> {{ $money($refundDue) }}</p>
                        </x-card>
                    @endif
                    <x-card :title="__('Stay')" icon="bi-calendar-range">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ __('Primary guest') }}</dt><dd class="col-sm-8">{{ $guest?->name }}@if ($guest?->phone) <span class="text-body-secondary">· {{ $guest->phone }}</span>@endif</dd>
                            <dt class="col-sm-4">{{ __('Dates') }}</dt><dd class="col-sm-8">{{ $reservation->check_in->format('D d M Y') }} → {{ $reservation->check_out->format('D d M Y') }} ({{ trans_choice(':count night|:count nights', $reservation->nights()) }})</dd>
                            <dt class="col-sm-4">{{ __('Party') }}</dt><dd class="col-sm-8">{{ trans_choice(':count adult|:count adults', $reservation->adults) }}@if ($reservation->children), {{ trans_choice(':count child|:count children', $reservation->children) }}@endif</dd>
                            <dt class="col-sm-4">{{ __('Rooms and cottages') }}</dt><dd class="col-sm-8">{{ $reservation->items->map($itemLabel)->implode(', ') }}</dd>
                            <dt class="col-sm-4">{{ __('Rate plan') }}</dt><dd class="col-sm-8">{{ $plan?->name }}</dd>
                            <dt class="col-sm-4">{{ __('Source') }}</dt><dd class="col-sm-8"><x-status-badge :status="$reservation->source" /></dd>
                            @if ($reservation->special_requests)<dt class="col-sm-4">{{ __('Requests') }}</dt><dd class="col-sm-8">{{ $reservation->special_requests }}</dd>@endif
                            @if ($reservation->internal_notes)<dt class="col-sm-4">{{ __('Internal notes') }}</dt><dd class="col-sm-8">{{ $reservation->internal_notes }}</dd>@endif
                        </dl>
                    </x-card>
                </div>
                <div class="col-xl-5">
                    <x-card :title="__('Price and deposit')" icon="bi-cash-stack">
                        <table class="table table-sm mb-0" data-reservation-totals>
                            <tr><td>{{ __('Subtotal') }}</td><td class="text-end font-monospace">{{ $money($reservation->subtotal) }}</td></tr>
                            @if ((float) $reservation->discount_total > 0)
                                <tr class="text-success"><td>{{ __('Discount') }}@if ($reservation->promo_code) ({{ $reservation->promo_code }})@endif</td><td class="text-end font-monospace">−{{ $money($reservation->discount_total) }}</td></tr>
                            @endif
                            <tr><td>{{ __('Taxes') }}</td><td class="text-end font-monospace">{{ $money($reservation->tax_total) }}</td></tr>
                            <tr class="fw-semibold"><td>{{ __('Grand total') }} ({{ $reservation->currency_code }})</td><td class="text-end font-monospace">{{ $money($reservation->grand_total) }}</td></tr>
                            <tr class="table-group-divider fw-semibold"><td>{{ __('Deposit (:percent%)', ['percent' => $percent($reservation->deposit_percent)]) }}</td><td class="text-end font-monospace" data-deposit>{{ $money($reservation->deposit_required) }}</td></tr>
                            <tr><td>{{ __('Paid') }}</td><td class="text-end font-monospace" data-paid>{{ $money($reservation->amount_paid) }}</td></tr>
                            <tr><td>{{ __('Balance') }}</td><td class="text-end font-monospace" data-balance>{{ $money($reservation->balance_due) }}</td></tr>
                        </table>
                        @if ($reservation->status === \Modules\Reservation\Enums\ReservationStatus::Tentative && $reservation->deposit_due_at)
                            <div class="small mt-2" data-deposit-due>{{ __('Deposit due by :time', ['time' => $reservation->deposit_due_at->setTimezone($timezone)->format('d M Y H:i')]) }}@if ($reservation->auto_cancel_unpaid) · {{ __('cancelled automatically if unpaid') }}@endif</div>
                        @endif
                        @if ($reservation->deposit_override_by)
                            <div class="small mt-1 text-warning-emphasis"><i class="bi bi-shield-exclamation"></i> {{ __('Deposit set outside the deposit policy by :user.', ['user' => $userNames[$reservation->deposit_override_by] ?? '#'.$reservation->deposit_override_by]) }}</div>
                        @endif
                        @if ($canUpdate)
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-3" data-bs-toggle="modal" data-bs-target="#change-deposit"><i class="bi bi-percent"></i> {{ __('Change deposit') }}</button>
                        @endif
                    </x-card>
                </div>
            </div>
        </div>

        {{-- Rooms: each item with its nightly prices --}}
        <div class="tab-pane fade" id="tab-rooms" role="tabpanel">
            @foreach ($reservation->items as $item)
                <x-card :title="$itemLabel($item)" icon="bi-house-door" body-class="p-0" data-reservation-item>
                    <div class="px-3 pt-2 small text-body-secondary">
                        <x-status-badge :status="$item->item_type" /> {{ trans_choice(':count adult|:count adults', $item->adults) }}@if ($item->children), {{ trans_choice(':count child|:count children', $item->children) }}@endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th class="ps-3">{{ __('Night') }}</th><th class="text-end">{{ __('Rate') }}</th><th class="text-end">{{ __('Extras') }}</th><th class="text-end">{{ __('Discount') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end pe-3">{{ __('Total') }}</th></tr></thead>
                            <tbody>
                                @foreach ($item->nights as $night)
                                    <tr>
                                        <td class="ps-3">{{ $night->stay_date->format('D d M') }}</td>
                                        <td class="text-end font-monospace">{{ $money($night->base_rate) }}</td>
                                        <td class="text-end font-monospace">{{ $money($night->extra_person_amount) }}</td>
                                        <td class="text-end font-monospace">{{ (float) $night->discount > 0 ? '−'.$money($night->discount) : '' }}</td>
                                        <td class="text-end font-monospace">{{ $money($night->tax_amount) }}</td>
                                        <td class="text-end pe-3 font-monospace">{{ $money($night->total_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="fw-semibold"><td class="ps-3" colspan="5">{{ __('Item total') }}</td><td class="text-end pe-3 font-monospace">{{ $money($item->total) }}</td></tr></tfoot>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>

        {{-- Guests --}}
        <div class="tab-pane fade" id="tab-guests" role="tabpanel">
            <x-card :title="__('Guests')" icon="bi-people" body-class="p-0">
                <table class="table mb-0" data-reservation-guests>
                    <thead><tr><th class="ps-3">{{ __('Guest') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Room or cottage') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($guests as ['row' => $row, 'guest' => $person])
                            <tr data-guest="{{ $row->guest_id }}">
                                <td class="ps-3">
                                    {{ $person?->name ?? '—' }}
                                    @if ($row->is_primary)<span class="badge text-bg-primary">{{ __('Primary') }}</span>@endif
                                    @if ($person?->isBlacklisted)<span class="badge text-bg-danger">{{ __('Blacklisted') }}</span>@endif
                                </td>
                                <td>{{ $person?->phone ?? '—' }}</td>
                                <td>{{ $row->reservation_item_id ? $itemLabel($reservation->items->firstWhere('id', $row->reservation_item_id) ?? $reservation->items->first()) : '—' }}</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @if ($canUpdate && ! $row->is_primary)
                                        <form method="POST" action="{{ route('reservation.bookings.guests.primary', [$reservation, $row]) }}" class="d-inline">
                                            @csrf @method('PUT')
                                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-star"></i> {{ __('Make primary') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('reservation.bookings.guests.destroy', [$reservation, $row]) }}" class="d-inline" data-confirm="{{ __('Remove this guest from the booking?') }}" data-confirm-variant="danger">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-person-dash"></i> {{ __('Remove') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
            @if (($canUpdate || $reservation->status === \Modules\Reservation\Enums\ReservationStatus::CheckedIn) && $reservation->items->count() > 1)
                @can('update', $reservation)
                    <a href="{{ route('reservation.bookings.rooming-list', $reservation) }}" class="btn btn-outline-primary mb-3" data-rooming-list-link><i class="bi bi-list-ol"></i> {{ __('Rooming list') }}</a>
                @endcan
            @endif
            @if ($canUpdate)
                <x-card :title="__('Add a guest')" icon="bi-person-plus">
                    <form method="POST" action="{{ route('reservation.bookings.guests.store', $reservation) }}" class="row g-2 align-items-end" data-add-guest>
                        @csrf
                        <x-form.select name="guest_id" :label="__('Guest')" wrapper-class="col-md-6 mb-0" :placeholder="__('Type a name, phone, email or ID…')" :tom-options="['remote' => route('guest.guests.search')]" required />
                        <x-form.select name="reservation_item_id" :label="__('Room or cottage')" wrapper-class="col-md-4 mb-0" :placeholder="__('Whole booking')"
                            :options="$reservation->items->mapWithKeys(fn ($item) => [$item->id => $itemLabel($item)])->all()" />
                        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button></div>
                    </form>
                </x-card>
            @endif
        </div>

        {{-- Tabs of other modules (e.g. Billing: Payments) --}}
        @foreach ($tabs as $extra)
            <div class="tab-pane fade" id="tab-{{ $extra['tab']->key }}" role="tabpanel">{!! $extra['content'] !!}</div>
        @endforeach

        {{-- History --}}
        <div class="tab-pane fade" id="tab-history" role="tabpanel">
            <x-card :title="__('Booking history')" icon="bi-clock-history" body-class="p-0">
                <ul class="list-group list-group-flush" data-reservation-history>
                    @forelse ($reservation->logs as $log)
                        <li class="list-group-item d-flex gap-3">
                            <span class="text-body-secondary small text-nowrap">{{ $log->created_at?->setTimezone($timezone)->format('d M Y H:i') }}</span>
                            <span class="flex-grow-1"><x-status-badge :status="$log->action" /> {{ $log->description }}</span>
                            <span class="text-body-secondary small text-nowrap">{{ $log->user_id ? ($userNames[$log->user_id] ?? '#'.$log->user_id) : __('System') }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">{{ __('No changes yet.') }}</li>
                    @endforelse
                </ul>
            </x-card>
            <x-audit-trail :entries="$history" :title="__('Field changes')" />
        </div>
    </div>

    @if ($canUpdate)
        <x-modal id="change-deposit" :title="__('Change deposit')" :show="$errors->has('deposit_percent')">
            <form method="POST" action="{{ route('reservation.bookings.deposit', $reservation) }}" id="change-deposit-form">
                @csrf @method('PUT')
                <x-form.input name="deposit_percent" type="number" :label="__('Deposit %')" :value="$percent($reservation->deposit_percent)" append="%" required
                    step="0.01" min="0" max="100" :help="$depositPolicy ? __('Policy :name: :range. Use 0 to waive the deposit.', ['name' => $depositPolicy->name, 'range' => collect([
                        $depositPolicy->terms->minPercent !== null ? __('at least :p%', ['p' => $percent($depositPolicy->terms->minPercent)]) : null,
                        $depositPolicy->terms->maxPercent !== null ? __('at most :p%', ['p' => $percent($depositPolicy->terms->maxPercent)]) : null,
                    ])->filter()->implode(', ') ?: __('any percent')]) : __('Use 0 to waive the deposit.')" />
                @cannot('reservation.deposit.override')
                    <p class="small text-body-secondary mb-0">{{ __('A deposit outside the policy needs a manager.') }}</p>
                @endcannot
            </form>
            <x-slot:footer>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                <button type="submit" form="change-deposit-form" class="btn btn-primary">{{ __('Save deposit') }}</button>
            </x-slot:footer>
        </x-modal>
    @endif
</x-layouts::app>
