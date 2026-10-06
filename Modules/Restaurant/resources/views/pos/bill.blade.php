{{--
    The POS bill screen (ARCHITECTURE §5.10.7, §10.3): before printing, discounts and the split with a
    preview from the server; once printed, each bill with what is due, payments, complimentary, receipt
    and void. Alpine (posBill) holds the screen; every amount comes from the server.
--}}
<x-layouts::pos :title="__('Bill · :order', ['order' => $order->order_no])" :terminal="$terminal">
    <x-slot:status>
        <a href="{{ route('pos.floor') }}" class="btn pos-btn btn-outline-secondary"><i class="bi bi-grid-3x3"></i> {{ __('Floor') }}</a>
        @if ($session)
            <span class="badge text-bg-success pos-bar__badge">{{ __('Session open') }}</span>
        @else
            <a href="{{ route('pos.main') }}" class="badge text-bg-warning pos-bar__badge text-decoration-none" data-no-session>{{ __('No session: payments need one') }}</a>
        @endif
    </x-slot:status>

    <div x-data="posIdle({{ $autoLockMinutes }})"></div>

    <div class="pos-bill" x-data="posBill(@js($state))" data-bill-screen="{{ $order->id }}">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h1 class="h4 mb-0" x-text="billing.where + ' · ' + billing.order_no"></h1>
            <span class="badge text-bg-secondary" x-text="billing.status_label" data-order-status></span>
            <span class="ms-auto"></span>
            <a :href="urls.order" class="btn pos-btn btn-outline-primary" x-show="open"><i class="bi bi-arrow-left"></i> {{ __('Back to the order') }}</a>
            <button type="button" class="btn pos-btn btn-outline-warning" x-show="billing.status === 'bill_printed' && billing.bills.every(b => Number(b.paid_total) === 0)" @click="reopen()" :disabled="busy" data-reopen>
                <i class="bi bi-unlock"></i> {{ __('Reopen order') }}
            </button>
        </div>

        <p class="alert alert-danger py-2" x-show="error" x-text="error" x-cloak data-bill-error></p>

        {{-- Before printing: discounts and the split. --}}
        <div class="pos-bill__grid" x-show="open">
            <section class="pos-card">
                <h2 class="h5">{{ __('Items') }}</h2>
                <ul class="list-unstyled mb-2">
                    <template x-for="line in billing.lines" :key="line.id">
                        <li class="pos-bill__line" :data-bill-line="line.name">
                            <span><span x-text="line.quantity + ' × ' + line.name"></span> <span class="badge text-bg-secondary" x-show="line.seat" x-text="'{{ __('Seat') }} ' + line.seat"></span>
                                <span class="badge text-bg-warning" x-show="line.discount" x-text="line.discount ? '−' + line.discount.value + (line.discount.type === 'percent' ? '%' : '') : ''"></span>
                                <span class="badge text-bg-info" x-show="line.meal_plan">{{ __('Meal plan') }}</span></span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="font-monospace" x-text="line.line_total"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" @click="startDiscount(line)" :aria-label="'{{ __('Discount') }} ' + line.name" data-discount-line><i class="bi bi-percent"></i></button>
                            </span>
                        </li>
                    </template>
                </ul>
                <button type="button" class="btn pos-btn btn-outline-warning w-100" @click="startDiscount()" data-discount-bill>
                    <i class="bi bi-percent"></i> {{ __('Discount the whole bill') }}
                    <span x-show="billing.discount" x-text="billing.discount ? '(−' + billing.discount.value + (billing.discount.type === 'percent' ? '%' : '') + ')' : ''"></span>
                </button>
                <p class="small text-body-secondary mt-2 mb-0">{{ __('Your discount limit') }}: <span x-text="discountLimit + '%'"></span></p>
                <div class="alert alert-info mt-3 mb-0 d-flex align-items-center gap-2" x-show="billing.redemption" x-cloak data-redemption>
                    <i class="bi bi-cup-hot"></i>
                    <span class="flex-grow-1" x-text="billing.redemption ? billing.redemption.period + ' · ' + billing.redemption.guest + ' (' + billing.redemption.code + ') · ' + billing.redemption.covers + ' {{ __('covers') }}' : ''"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearMeal()" :disabled="busy" data-clear-meal>{{ __('Remove') }}</button>
                </div>
                <button type="button" class="btn pos-btn btn-outline-info w-100 mt-2" x-show="can.redeem && ! billing.redemption" @click="startMeal()" data-meal-plan>
                    <i class="bi bi-cup-hot"></i> {{ __('Guest meal plan') }}
                </button>
            </section>

            <section class="pos-card">
                <h2 class="h5">{{ __('Split') }}</h2>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <template x-for="option in modes" :key="option.value">
                        <button type="button" class="btn pos-btn" :class="mode === option.value ? 'btn-primary' : 'btn-outline-primary'" @click="mode = option.value" :data-mode="option.value" x-text="option.label"></button>
                    </template>
                </div>
                <div class="d-flex align-items-center gap-2 mb-3" x-show="['equal', 'item'].includes(mode)">
                    <span>{{ __('Bills') }}</span>
                    <button type="button" class="btn pos-btn btn-outline-secondary" @click="count = Math.max(2, count - 1)" aria-label="{{ __('One less') }}"><i class="bi bi-dash-lg"></i></button>
                    <span class="fs-4 font-monospace" x-text="count" data-split-count></span>
                    <button type="button" class="btn pos-btn btn-outline-secondary" @click="count = Math.min(20, count + 1)" aria-label="{{ __('One more') }}" data-split-more><i class="bi bi-plus-lg"></i></button>
                </div>
                <div x-show="mode === 'amount'" class="mb-3">
                    <template x-for="(amount, index) in amounts" :key="index">
                        <div class="input-group mb-2">
                            <span class="input-group-text" x-text="'{{ __('Bill') }} ' + (index + 1)"></span>
                            <input type="number" step="0.01" min="0" class="form-control" x-model="amounts[index]" @change="preview()" :aria-label="'{{ __('Amount of bill') }} ' + (index + 1)" :data-amount="index">
                        </div>
                    </template>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" @click="amounts.push(''); preview()">{{ __('Add a bill') }}</button>
                        <button type="button" class="btn btn-outline-secondary" @click="restAmount()" data-rest>{{ __('Last bill takes the rest') }}</button>
                    </div>
                </div>
                <div x-show="mode === 'item'" class="mb-3 table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ __('Item') }}</th><template x-for="index in count"><th class="text-center" x-text="'{{ __('Bill') }} ' + index"></th></template></tr></thead>
                        <tbody>
                            <template x-for="line in billing.lines" :key="line.id">
                                <tr :data-assign="line.name">
                                    <td x-text="line.quantity + ' × ' + line.name"></td>
                                    <template x-for="(units, index) in assign[line.id]" :key="index">
                                        <td class="text-center text-nowrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveUnit(line, index, -1)" :disabled="units === 0" aria-label="{{ __('One less') }}">−</button>
                                            <span class="font-monospace mx-1" x-text="units"></span>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" @click="moveUnit(line, index, 1)" aria-label="{{ __('One more') }}" :data-give="index">+</button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="small text-body-secondary" x-show="mode === 'seat'" x-text="billing.seats.length > 1 ? '{{ __('One bill per seat; items without a seat are shared.') }}' : '{{ __('Give the items seats on the order first.') }}'"></p>

                <div class="pos-bill__previews">
                    <template x-for="(bill, index) in bills" :key="index">
                        <div class="pos-bill__preview" data-preview>
                            <div class="fw-semibold" x-text="bill.label || '{{ __('Bill') }}'"></div>
                            <div class="pos-bill__row small"><span>{{ __('Subtotal') }}</span><span class="font-monospace" x-text="bill.subtotal"></span></div>
                            <div class="pos-bill__row small" x-show="Number(bill.discount_total) > 0"><span>{{ __('Discount') }}</span><span class="font-monospace" x-text="'−' + bill.discount_total"></span></div>
                            <template x-for="tax in bill.tax_breakdown" :key="tax.code">
                                <div class="pos-bill__row small"><span x-text="tax.name + (bill.inclusive ? ' ({{ __('included') }})' : '')"></span><span class="font-monospace" x-text="tax.amount"></span></div>
                            </template>
                            <div class="pos-bill__row fw-bold"><span>{{ __('Total') }}</span><span class="font-monospace" x-text="bill.grand_total" data-preview-total></span></div>
                        </div>
                    </template>
                </div>
                <p class="small text-warning mt-2" x-show="billing.pending > 0">{{ __('Send or remove the items not yet sent to the kitchen first.') }}</p>
                <button type="button" class="btn btn-success btn-lg pos-btn w-100 mt-2" @click="print()" :disabled="busy || bills.length === 0 || billing.pending > 0" data-print-bill>
                    <i class="bi bi-printer"></i> <span x-text="bills.length > 1 ? '{{ __('Print :count bills') }}'.replace(':count', bills.length) : '{{ __('Print bill') }}'"></span>
                </button>
            </section>
        </div>

        {{-- Printed bills: payments, complimentary, receipts, voids. --}}
        <div class="pos-bill__bills" x-show="! open">
            <template x-for="bill in billing.bills" :key="bill.id">
                <section class="pos-card" :data-bill="bill.bill_no">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h2 class="h5 mb-0" x-text="bill.bill_no"></h2>
                        <span class="text-body-secondary" x-text="bill.label"></span>
                        <span class="badge ms-auto" :class="{ 'text-bg-warning': bill.status === 'printed', 'text-bg-success': bill.status === 'settled', 'text-bg-danger': bill.status === 'voided' }" x-text="bill.status_label" :data-bill-status="bill.status"></span>
                    </div>
                    <div class="mb-2">
                        <div class="pos-bill__row"><span>{{ __('Total') }}</span><span class="font-monospace fw-semibold" x-text="bill.grand_total"></span></div>
                        <div class="pos-bill__row"><span>{{ __('Paid') }}</span><span class="font-monospace" x-text="bill.paid_total"></span></div>
                        <div class="pos-bill__row" x-show="Number(bill.tip_total) > 0"><span>{{ __('Tips') }}</span><span class="font-monospace" x-text="bill.tip_total"></span></div>
                        <div class="pos-bill__row fs-5 fw-bold"><span>{{ __('Due') }}</span><span class="font-monospace" x-text="bill.due" :data-due="bill.due"></span></div>
                    </div>
                    <ul class="list-unstyled small mb-2">
                        <template x-for="payment in bill.payments">
                            <li class="d-flex justify-content-between" :class="{ 'text-danger': payment.refund }">
                                <span x-text="payment.method + (payment.charged_to ? ' · ' + payment.charged_to : '') + (payment.reference ? ' · ' + payment.reference : '') + (Number(payment.change) > 0 ? ' · {{ __('change') }} ' + payment.change : '')"></span>
                                <span class="font-monospace" x-text="payment.amount + (Number(payment.tip) !== 0 ? ' + ' + payment.tip : '')"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <template x-if="bill.status === 'printed' && can.settle">
                            <button type="button" class="btn btn-success btn-lg pos-btn flex-grow-1" @click="startPayment(bill)" data-pay><i class="bi bi-cash-coin"></i> {{ __('Pay') }}</button>
                        </template>
                        <template x-if="bill.status === 'printed' && billing.type === 'staff_meal' && Number(bill.paid_total) === 0">
                            <button type="button" class="btn btn-warning btn-lg pos-btn" @click="startException('comp', bill, 'staff_meal')" data-staff-meal><i class="bi bi-person-badge"></i> {{ __('Settle as staff meal') }}</button>
                        </template>
                        <template x-if="bill.status === 'printed' && can.settle && Number(bill.paid_total) === 0">
                            <button type="button" class="btn btn-outline-warning pos-btn" @click="startException('comp', bill)" data-comp>{{ __('Complimentary') }}</button>
                        </template>
                        <button type="button" class="btn btn-outline-secondary pos-btn" @click="reprint(bill.print_url)" x-show="bill.status === 'printed'" data-reprint><i class="bi bi-printer"></i> {{ __('Reprint') }}</button>
                        <a :href="bill.receipt_url" target="_blank" class="btn btn-outline-secondary pos-btn" x-show="bill.status === 'settled'" data-receipt><i class="bi bi-receipt"></i> {{ __('Receipt') }}</a>
                        <template x-if="bill.status === 'settled' && can.settle">
                            <button type="button" class="btn btn-outline-danger pos-btn" @click="startException('void', bill)" data-void-bill>{{ __('Void') }}</button>
                        </template>
                    </div>
                </section>
            </template>
        </div>
        <a :href="urls.floor" class="btn btn-primary btn-lg pos-btn mt-3" x-show="['settled', 'voided'].includes(billing.status)" x-cloak data-done><i class="bi bi-grid-3x3"></i> {{ __('Back to the floor') }}</a>

        {{-- Discount sheet --}}
        <div class="pos-sheet" x-show="discounting" x-cloak>
            <template x-if="discounting">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" aria-label="{{ __('Discount') }}" data-discount-sheet>
                    <h2 class="h4" x-text="discounting.line ? '{{ __('Discount') }}: ' + discounting.line.name : '{{ __('Discount the whole bill') }}'"></h2>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn pos-btn" :class="discounting.type === 'percent' ? 'btn-primary' : 'btn-outline-primary'" @click="discounting.type = 'percent'">%</button>
                        <button type="button" class="btn pos-btn" :class="discounting.type === 'amount' ? 'btn-primary' : 'btn-outline-primary'" @click="discounting.type = 'amount'">{{ __('Amount') }}</button>
                        <input type="number" step="0.01" min="0" class="form-control form-control-lg" x-model="discounting.value" aria-label="{{ __('Discount') }}" data-discount-value>
                    </div>
                    <label class="form-label" for="discount-reason">{{ __('Reason') }}</label>
                    <input type="text" maxlength="300" id="discount-reason" class="form-control mb-3" x-model="discounting.reason" placeholder="{{ __('e.g. regular guest, birthday, service delay') }}" data-discount-reason>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-lg pos-btn flex-grow-1" @click="saveDiscount()" :disabled="busy" data-save-discount>{{ __('Apply') }}</button>
                        <button type="button" class="btn btn-outline-danger btn-lg pos-btn" @click="saveDiscount(null, true)" :disabled="busy">{{ __('Remove') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="discounting = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Payment sheet --}}
        <div class="pos-sheet" x-show="paying" x-cloak>
            <template x-if="paying">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" aria-label="{{ __('Pay') }}" data-payment-sheet>
                    <h2 class="h4"><span x-text="paying.bill.bill_no"></span> · {{ __('due') }} <span class="font-monospace" x-text="paying.bill.due"></span></h2>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <template x-for="method in methods" :key="method.value">
                            <button type="button" class="btn pos-btn" :class="paying.method === method.value ? 'btn-primary' : 'btn-outline-primary'" @click="pickMethod(method.value)" :data-method="method.value" x-text="method.label"></button>
                        </template>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label" for="pay-amount">{{ __('Amount') }}</label>
                            <input type="number" step="0.01" min="0" id="pay-amount" class="form-control form-control-lg font-monospace" x-model="paying.amount" data-pay-amount>
                        </div>
                        <div class="col-6" x-show="! ['room_charge', 'city_ledger'].includes(paying.method)">
                            <label class="form-label" for="pay-tip">{{ __('Tip') }}</label>
                            <input type="number" step="0.01" min="0" id="pay-tip" class="form-control form-control-lg font-monospace" x-model="paying.tip" placeholder="0.00" data-pay-tip>
                        </div>
                    </div>
                    <div x-show="paying.method === 'cash'" class="mb-2">
                        <label class="form-label" for="pay-tendered">{{ __('Cash given') }}</label>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <template x-for="note in quickTenders(Number(paying.amount || 0) + Number(paying.tip || 0))">
                                <button type="button" class="btn btn-outline-secondary pos-btn font-monospace" @click="paying.tendered = note.toFixed(2)" x-text="note.toFixed(2)" :data-tender="note"></button>
                            </template>
                        </div>
                        <input type="number" step="0.01" min="0" id="pay-tendered" class="form-control form-control-lg font-monospace" x-model="paying.tendered" data-pay-tendered>
                        <p class="fs-5 mt-2 mb-0" x-show="change !== null" :class="change < 0 ? 'text-danger' : 'text-success'">{{ __('Change') }}: <span class="font-monospace fw-bold" x-text="money(change)" data-change></span></p>
                    </div>
                    <div x-show="paying.method === 'room_charge'" class="mb-2" data-room-charge>
                        <label class="form-label" for="stay-search">{{ __('Room, guest or booking') }}</label>
                        <input type="search" id="stay-search" class="form-control mb-2" x-model="paying.term" @input.debounce.300ms="searchStays(paying.term)" placeholder="{{ __('e.g. 402 or Rahim') }}" autocomplete="off">
                        <div class="pos-stays">
                            <template x-for="stay in stays" :key="stay.reservationId">
                                <button type="button" class="pos-stay" :class="{ 'pos-stay--chosen': paying.stay && paying.stay.reservationId === stay.reservationId }" @click="paying.stay = stay"
                                    :disabled="stay.noRoomCharges" :data-stay="stay.code">
                                    <span class="fw-semibold" x-text="stay.rooms.join(', ') + ' · ' + stay.guestName"></span>
                                    <span class="small" x-text="stay.code + ' · {{ __('folio') }} ' + stay.folioNo + ' · {{ __('balance') }} ' + stay.balance + (stay.creditLeft !== null ? ' · {{ __('credit left') }} ' + stay.creditLeft : '')"></span>
                                    <span class="badge text-bg-warning" x-show="stay.noRoomCharges">{{ __('No room charges') }}</span>
                                </button>
                            </template>
                            <p class="small text-body-secondary mb-0" x-show="stays.length === 0">{{ __('No guest in house matches.') }}</p>
                        </div>
                        <div class="mt-2" x-show="paying.stay">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="form-label mb-0">{{ __('Guest signature (optional)') }}</span>
                                <button type="button" class="btn btn-sm btn-link" @click="clearSignature()">{{ __('Clear') }}</button>
                            </div>
                            <canvas x-ref="signature" width="600" height="160" class="pos-signature" @pointerdown.prevent="sign($event, 'start')" @pointermove.prevent="sign($event, 'move')"
                                @pointerup="sign($event, 'end')" @pointerleave="sign($event, 'end')" aria-label="{{ __('Guest signature') }}" data-signature></canvas>
                        </div>
                    </div>
                    <div x-show="paying.method === 'city_ledger'" class="mb-2" data-city-ledger>
                        <label class="form-label" for="company-search">{{ __('Company') }}</label>
                        <input type="search" id="company-search" class="form-control mb-2" x-model="paying.term" @input.debounce.300ms="searchCompanies(paying.term)" autocomplete="off">
                        <div class="pos-stays">
                            <template x-for="company in companies" :key="company.companyId">
                                <button type="button" class="pos-stay" :class="{ 'pos-stay--chosen': paying.company && paying.company.companyId === company.companyId }" @click="paying.company = company" :data-company="company.name">
                                    <span class="fw-semibold" x-text="company.name"></span>
                                    <span class="small" x-text="'{{ __('owes') }} ' + company.owed + (company.creditLeft !== null ? ' · {{ __('credit left') }} ' + company.creditLeft : '')"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div x-show="['card', 'wallet', 'bank_transfer'].includes(paying.method)" class="mb-2">
                        <label class="form-label" for="pay-reference">{{ __('Reference') }}</label>
                        <input type="text" maxlength="100" id="pay-reference" class="form-control" x-model="paying.reference" placeholder="{{ __('Approval code or last 4 digits') }}" data-pay-reference>
                    </div>
                    <p class="alert alert-danger py-2" x-show="error" x-text="error"></p>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-success btn-lg pos-btn flex-grow-1" @click="pay()" :disabled="busy" data-confirm-pay><i class="bi bi-check-lg"></i> {{ __('Take payment') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="paying = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Complimentary / void sheet --}}
        <div class="pos-sheet" x-show="exception" x-cloak>
            <template x-if="exception">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" :aria-label="exception.kind === 'comp' ? '{{ __('Complimentary') }}' : '{{ __('Void') }}'" data-exception-sheet>
                    <h2 class="h4" x-text="(exception.kind === 'comp' ? '{{ __('Complimentary') }}' : '{{ __('Void') }}') + ' · ' + exception.bill.bill_no"></h2>
                    <template x-if="exception.kind === 'comp'">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <template x-for="reason in compReasons" :key="reason.value">
                                <button type="button" class="btn pos-btn" :class="exception.comp_reason === reason.value ? 'btn-warning' : 'btn-outline-warning'" @click="exception.comp_reason = reason.value" :data-comp-reason="reason.value" x-text="reason.label"></button>
                            </template>
                        </div>
                    </template>
                    <template x-if="exception.kind === 'comp'">
                        <input type="text" maxlength="300" class="form-control mb-3" x-model="exception.note" placeholder="{{ __('Note') }}" aria-label="{{ __('Note') }}">
                    </template>
                    <template x-if="exception.kind === 'void'">
                        <div>
                            <p class="text-body-secondary">{{ __('The payments are refunded in this session. Only bills of today\'s business date can be voided.') }}</p>
                            <input type="text" maxlength="300" class="form-control mb-2" x-model="exception.reason" placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}" data-void-reason>
                            <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="food-prepared" x-model="exception.food_prepared"><label class="form-check-label" for="food-prepared">{{ __('The food was prepared') }}</label></div>
                        </div>
                    </template>
                    <p class="alert alert-danger py-2" x-show="error" x-text="error"></p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-lg pos-btn flex-grow-1" :class="exception.kind === 'comp' ? 'btn-warning' : 'btn-danger'" @click="saveException()" :disabled="busy" data-confirm-exception>{{ __('Confirm') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="exception = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Meal plan sheet --}}
        <div class="pos-sheet" x-show="meal" x-cloak>
            <template x-if="meal">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" aria-label="{{ __('Guest meal plan') }}" data-meal-sheet>
                    <h2 class="h4"><i class="bi bi-cup-hot"></i> {{ __('Guest meal plan') }}</h2>
                    <div x-show="! meal.stay">
                        <input type="search" class="form-control mb-2" x-model="meal.term" @input.debounce.300ms="searchStays(meal.term)" placeholder="{{ __('Room, guest or booking') }}" aria-label="{{ __('Room, guest or booking') }}" autocomplete="off" data-meal-search>
                        <div class="pos-stays">
                            <template x-for="stay in stays" :key="stay.reservationId">
                                <button type="button" class="pos-stay" @click="pickMealStay(stay)" :data-meal-stay="stay.code">
                                    <span class="fw-semibold" x-text="stay.rooms.join(', ') + ' · ' + stay.guestName"></span><span class="small" x-text="stay.code"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <div x-show="meal.stay">
                        <p class="fw-semibold mb-2" x-text="meal.stay ? meal.stay.rooms.join(', ') + ' · ' + meal.stay.guestName + ' (' + meal.stay.code + ')' : ''"></p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <template x-for="period in periods" :key="period.value">
                                <button type="button" class="btn pos-btn" :class="meal.period === period.value ? 'btn-info' : 'btn-outline-info'" @click="meal.period = period.value; mealLeft()" :data-period="period.value" x-text="period.label"></button>
                            </template>
                        </div>
                        <p class="mb-3" x-show="meal.left" data-meal-left
                            x-text="meal.left ? (meal.left.plans.join(', ') + ' · ' + meal.left.left + ' {{ __('of') }} ' + meal.left.entitled + ' {{ __('covers left') }}') : ''"></p>
                        <div class="row g-2 mb-3">
                            <div class="col-6"><label class="form-label" for="meal-adults">{{ __('Adults') }}</label><input type="number" min="0" max="50" id="meal-adults" class="form-control form-control-lg" x-model.number="meal.adults" data-meal-adults></div>
                            <div class="col-6"><label class="form-label" for="meal-children">{{ __('Children') }}</label><input type="number" min="0" max="50" id="meal-children" class="form-control form-control-lg" x-model.number="meal.children"></div>
                        </div>
                        <p class="small text-body-secondary">{{ __('Items on the meal-plan menu go on the bill at nothing; anything else is billed as usual.') }}</p>
                    </div>
                    <p class="alert alert-danger py-2" x-show="error" x-text="error"></p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-info btn-lg pos-btn flex-grow-1" @click="redeem()" :disabled="busy || ! meal.stay" data-redeem>{{ __('Redeem') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="meal = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Manager approval sheet --}}
        <div class="pos-sheet" x-show="approval" x-cloak>
            <template x-if="approval">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" aria-label="{{ __('Manager approval') }}" data-approval-sheet>
                    <h2 class="h4"><i class="bi bi-shield-lock"></i> {{ __('Manager approval') }}</h2>
                    <p class="text-body-secondary" x-text="approval.why"></p>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <template x-for="(name, id) in approvers[approval.action]" :key="id">
                            <button type="button" class="btn pos-btn" :class="approval.managerId == id ? 'btn-warning' : 'btn-outline-warning'" @click="approval.managerId = Number(id)" :data-approver="id" x-text="name"></button>
                        </template>
                        <p class="text-danger mb-0" x-show="Object.keys(approvers[approval.action] ?? {}).length === 0">{{ __('No manager with a POS PIN works in this outlet.') }}</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <label class="visually-hidden" for="bill-approval-pin">{{ __('Manager PIN') }}</label>
                        <input type="password" inputmode="numeric" maxlength="6" id="bill-approval-pin" class="form-control form-control-lg pos-approval__pin" x-model="approval.pin" placeholder="{{ __('PIN') }}" autocomplete="off">
                        <button type="button" class="btn btn-warning pos-btn" @click="approve()" :disabled="! approval.managerId || approval.pin.length < 4 || busy" data-approve>{{ __('Approve') }}</button>
                        <button type="button" class="btn btn-outline-secondary pos-btn" @click="approval = null">{{ __('Cancel') }}</button>
                    </div>
                    <p class="text-danger small mt-2 mb-0" x-show="approval.error" x-text="approval.error"></p>
                </div>
            </template>
        </div>
    </div>
</x-layouts::pos>
