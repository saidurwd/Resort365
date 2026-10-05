{{--
    The POS order screen (ARCHITECTURE §5.10.5): menu on the left, the ticket on the right. Alpine
    (posOrder) holds what is on screen; every change is sent to the server, which answers with the order.
--}}
<x-layouts::pos :title="$order->order_no" :terminal="$terminal">
    <x-slot:status>
        <a href="{{ route('pos.floor') }}" class="btn pos-btn btn-outline-secondary" data-back-to-floor><i class="bi bi-grid-3x3"></i> {{ __('Floor') }}</a>
    </x-slot:status>

    <div x-data="posIdle({{ $autoLockMinutes }})"></div>

    <div class="pos-order" x-data="posOrder(@js($state))">
        <section class="pos-card pos-order__menu">
            <div class="d-flex gap-2 mb-2">
                <label class="visually-hidden" for="menu-search">{{ __('Find an item') }}</label>
                <input type="search" id="menu-search" class="form-control form-control-lg" x-model="search" placeholder="{{ __('Find by name or code') }}" autocomplete="off" data-menu-search>
            </div>
            <div class="pos-categories mb-2">
                <button type="button" class="btn pos-btn" :class="category === null ? 'btn-primary' : 'btn-outline-primary'" @click="category = null">{{ __('All') }}</button>
                <template x-for="cat in menu.categories" :key="cat.id">
                    <button type="button" class="btn pos-btn" :class="category === cat.id ? 'btn-primary' : 'btn-outline-primary'" @click="category = cat.id" x-text="cat.name" :data-category="cat.name"></button>
                </template>
            </div>
            <div class="pos-items">
                <template x-for="item in items" :key="item.id">
                    <button type="button" class="pos-item" :class="{ 'pos-item--off': unavailable(item) }" @click="pick(item)" :data-item="item.code" :disabled="busy">
                        <span class="pos-item__name" x-text="item.name"></span>
                        <span class="pos-item__meta">
                            <span x-text="item.code"></span>
                            <span class="font-monospace" x-text="item.open ? '{{ __('Open price') }}' : variantFor(item).price"></span>
                        </span>
                        <span class="badge text-bg-danger" x-show="item.variants.every(v => v.sold_out)">{{ __('Sold out') }}</span>
                    </button>
                </template>
                <p class="text-body-secondary" x-show="items.length === 0">{{ __('No items match.') }}</p>
            </div>
        </section>

        <section class="pos-card pos-order__ticket" data-ticket>
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h1 class="h5 mb-0"><span x-text="order.table ? '{{ __('Table') }} ' + order.table : order.type_label"></span></h1>
                    <div class="small text-body-secondary"><span x-text="order.order_no" data-order-no></span> · <span x-text="order.covers"></span> {{ __('covers') }}</div>
                </div>
                <div class="dropdown">
                    <button type="button" class="btn pos-btn btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('More') }}" data-more><i class="bi bi-three-dots"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item" @click="tool = 'transfer'" data-tool="transfer"><i class="bi bi-arrow-left-right"></i> {{ __('Move to another table') }}</button></li>
                        <li><button type="button" class="dropdown-item" @click="tool = 'merge'" data-tool="merge"><i class="bi bi-union"></i> {{ __('Merge another order in') }}</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button type="button" class="dropdown-item text-danger" @click="cancel()" data-cancel-order><i class="bi bi-x-circle"></i> {{ __('Cancel order') }}</button></li>
                    </ul>
                </div>
            </div>

            <p class="alert alert-danger py-2 mb-2" x-show="error" x-text="error" x-cloak data-order-error></p>
            <p class="alert alert-success py-2 mb-2" x-show="message && ! error" x-text="message" x-cloak data-order-message></p>

            <ul class="pos-lines list-unstyled mb-2">
                <template x-for="line in lines" :key="line.id">
                    <li class="pos-line" :class="{ 'pos-line--voided': line.status === 'voided', 'pos-line--held': line.held, 'pos-line--selected': selected && selected.id === line.id }"
                        @click="line.status === 'voided' ? null : (selected = selected && selected.id === line.id ? null : line)" :data-line="line.name">
                        <div class="d-flex justify-content-between gap-2">
                            <span><span class="fw-semibold" x-text="line.quantity + ' × ' + line.name"></span> <span x-show="line.variant" x-text="'(' + line.variant + ')'"></span></span>
                            <span class="font-monospace" x-text="line.line_total"></span>
                        </div>
                        <div class="small text-body-secondary">
                            <template x-for="mod in line.modifiers"><span class="me-2" x-text="'+ ' + mod.name"></span></template>
                            <span x-show="line.notes" class="fst-italic" x-text="line.notes"></span>
                        </div>
                        <div class="small d-flex flex-wrap gap-1 mt-1">
                            <span class="badge text-bg-secondary" x-text="line.course_label"></span>
                            <span class="badge text-bg-secondary" x-show="line.seat" x-text="'{{ __('Seat') }} ' + line.seat"></span>
                            <span class="badge" :class="line.held ? 'text-bg-warning' : 'text-bg-' + line.status_color"
                                x-text="line.held ? '{{ __('On hold') }}' : line.status_label" :data-line-status="line.status"></span>
                            <span class="small text-danger" x-show="line.void_reason" x-text="line.void_reason"></span>
                        </div>

                        <div class="pos-line__actions" x-show="selected && selected.id === line.id" @click.stop>
                            <template x-if="line.status === 'pending'">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn pos-btn btn-outline-secondary" @click="change(line, { quantity: line.quantity - 1 })" :disabled="line.quantity <= 1 || busy" aria-label="{{ __('One less') }}"><i class="bi bi-dash-lg"></i></button>
                                    <button type="button" class="btn pos-btn btn-outline-secondary" @click="change(line, { quantity: line.quantity + 1 })" :disabled="busy" aria-label="{{ __('One more') }}" data-line-more><i class="bi bi-plus-lg"></i></button>
                                    <button type="button" class="btn pos-btn btn-outline-warning" @click="change(line, { held: ! line.held })" :disabled="busy" data-line-hold x-text="line.held ? '{{ __('Release') }}' : '{{ __('Hold') }}'"></button>
                                    <button type="button" class="btn pos-btn btn-outline-danger" @click="remove(line)" :disabled="busy" data-line-remove><i class="bi bi-trash"></i> {{ __('Remove') }}</button>
                                </div>
                            </template>
                            <template x-if="line.status !== 'pending' && line.status !== 'voided'">
                                <button type="button" class="btn pos-btn btn-outline-danger" @click="startVoid(line)" data-line-void><i class="bi bi-x-octagon"></i> {{ __('Void') }}</button>
                            </template>
                        </div>
                    </li>
                </template>
                <li class="text-body-secondary py-3" x-show="lines.length === 0">{{ __('Tap items on the menu to add them.') }}</li>
            </ul>

            <div class="d-flex justify-content-between fw-semibold fs-5 border-top pt-2 mb-2">
                <span>{{ __('Subtotal') }}</span><span class="font-monospace" data-subtotal>{{ $currency }} <span x-text="order.subtotal"></span></span>
            </div>

            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success btn-lg pos-btn" @click="send()" :disabled="busy || order.pending === 0" data-send>
                    <i class="bi bi-send"></i> {{ __('Send') }} <span class="badge text-bg-light" x-show="order.pending" x-text="order.pending"></span>
                </button>
                <a :href="urls.bill" class="btn btn-outline-primary btn-lg pos-btn" :class="{ disabled: order.lines.length === 0 }" data-bill-button><i class="bi bi-receipt"></i> {{ __('Bill') }}</a>
                <template x-for="course in order.held" :key="course">
                    <button type="button" class="btn btn-warning pos-btn" @click="send(course)" :disabled="busy" :data-fire="course">
                        <i class="bi bi-fire"></i> {{ __('Fire') }} <span x-text="courses.find(c => c.value === course)?.label"></span>
                    </button>
                </template>
            </div>

            <div class="small mt-3" x-show="order.kots.length">
                <span class="text-body-secondary">{{ __('Kitchen tickets') }}:</span>
                <template x-for="kot in order.kots" :key="kot.id">
                    <a :href="kot.url" target="_blank" class="me-2" :data-kot="kot.no" x-text="'#' + kot.no + (kot.type === 'void' ? ' ({{ __('void') }})' : '')"></a>
                </template>
            </div>
        </section>

        {{-- Item options: variant, modifiers, quantity, course, seat, hold, notes, open price. --}}
        <div class="pos-sheet" x-show="picking" x-cloak @keydown.escape.window="picking = null">
            <template x-if="picking">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" :aria-label="picking.item.name" data-item-sheet>
                    <h2 class="h4" x-text="picking.item.name"></h2>
                    <div class="mb-3" x-show="picking.item.variants.length > 1">
                        <div class="form-label">{{ __('Size') }}</div>
                        <div class="d-flex flex-wrap gap-2">
                            <template x-for="variant in picking.item.variants" :key="variant.id">
                                <button type="button" class="btn pos-btn" :class="picking.variantId === variant.id ? 'btn-primary' : 'btn-outline-primary'" @click="picking.variantId = variant.id"
                                    :disabled="variant.sold_out || variant.out_of_schedule" :data-variant="variant.name" x-text="variant.name + ' · ' + variant.price"></button>
                            </template>
                        </div>
                    </div>
                    <template x-for="group in picking.item.groups" :key="group.id">
                        <div class="mb-3">
                            <div class="form-label">
                                <span x-text="group.name"></span>
                                <span class="small text-body-secondary" x-show="group.min > 0">· {{ __('required') }}</span>
                                <span class="small text-body-secondary" x-show="group.max > 1" x-text="'· {{ __('up to') }} ' + group.max"></span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <template x-for="option in group.options" :key="option.id">
                                    <button type="button" class="btn pos-btn" :class="chosen(group).includes(option.id) ? 'btn-primary' : 'btn-outline-primary'" @click="toggleModifier(group, option)" :data-modifier="option.name">
                                        <span x-text="option.name"></span> <span class="small" x-show="Number(option.price) !== 0" x-text="'+' + option.price"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div class="mb-3" x-show="picking.item.open">
                        <label class="form-label" for="open-price">{{ __('Price') }}</label>
                        <input type="number" step="0.01" min="0" id="open-price" class="form-control form-control-lg" x-model="picking.openPrice" :disabled="! mayPriceOpenItems" data-open-price>
                        <p class="small text-danger mt-1" x-show="! mayPriceOpenItems">{{ __('Only a manager or cashier can price an open item.') }}</p>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="form-label">{{ __('Quantity') }}</div>
                            <div class="input-group">
                                <button type="button" class="btn pos-btn btn-outline-secondary" @click="picking.quantity = Math.max(1, picking.quantity - 1)" aria-label="{{ __('One less') }}"><i class="bi bi-dash-lg"></i></button>
                                <input type="number" min="1" max="99" class="form-control text-center" x-model.number="picking.quantity" aria-label="{{ __('Quantity') }}" data-quantity>
                                <button type="button" class="btn pos-btn btn-outline-secondary" @click="picking.quantity = Math.min(99, picking.quantity + 1)" aria-label="{{ __('One more') }}"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="pick-course">{{ __('Course') }}</label>
                            <select id="pick-course" class="form-select pos-btn" x-model="picking.course">
                                <template x-for="course in courses" :key="course.value"><option :value="course.value" x-text="course.label" :selected="course.value === picking.course"></option></template>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="pick-seat">{{ __('Seat') }}</label>
                            <input type="number" min="1" max="50" id="pick-seat" class="form-control pos-btn" x-model="picking.seat">
                        </div>
                        <div class="col-6 col-md-3 d-flex align-items-end">
                            <div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="pick-held" x-model="picking.held"><label class="form-check-label" for="pick-held">{{ __('Hold until fired') }}</label></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pick-notes">{{ __('Note for the kitchen') }}</label>
                        <input type="text" maxlength="300" id="pick-notes" class="form-control" x-model="picking.notes" placeholder="{{ __('e.g. less spicy, no onion') }}">
                    </div>
                    <p class="alert alert-danger py-2" x-show="error" x-text="error"></p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-lg pos-btn flex-grow-1" @click="add(picking)" :disabled="busy" data-add-item><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="picking = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Void a line sent to the kitchen: reason, wastage, and a manager's PIN when needed. --}}
        <div class="pos-sheet" x-show="voiding" x-cloak>
            <template x-if="voiding">
                <div class="pos-sheet__body" role="dialog" aria-modal="true" aria-label="{{ __('Void') }}" data-void-sheet>
                    <h2 class="h4">{{ __('Void') }} <span x-text="voiding.line.quantity + ' × ' + voiding.line.name"></span></h2>
                    <div class="form-label">{{ __('Reason') }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <template x-for="reason in reasons" :key="reason.value">
                            <button type="button" class="btn pos-btn" :class="voiding.reason === reason.value ? 'btn-danger' : 'btn-outline-danger'" @click="voiding.reason = reason.value" :data-reason="reason.value" x-text="reason.label"></button>
                        </template>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="void-note">{{ __('Note') }}</label>
                        <input type="text" maxlength="300" id="void-note" class="form-control" x-model="voiding.note" data-void-note>
                    </div>
                    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="void-wastage" x-model="voiding.wastage"><label class="form-check-label" for="void-wastage">{{ __('Already made (record as wastage)') }}</label></div>

                    <div class="pos-approval" x-show="voiding.asking" data-approval-panel>
                        <h3 class="h5"><i class="bi bi-shield-lock"></i> {{ __('Manager approval') }}</h3>
                        @if ($approvers === [])
                            <p class="text-danger mb-0">{{ __('No manager with a POS PIN works in this outlet.') }}</p>
                        @else
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @foreach ($approvers as $id => $name)
                                    <button type="button" class="btn pos-btn" :class="managerId == {{ $id }} ? 'btn-warning' : 'btn-outline-warning'" @click="managerId = {{ $id }}" data-approver="{{ $id }}">{{ $name }}</button>
                                @endforeach
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <label class="visually-hidden" for="void-pin">{{ __('Manager PIN') }}</label>
                                <input type="password" inputmode="numeric" maxlength="6" id="void-pin" class="form-control form-control-lg pos-approval__pin" x-model="pin" placeholder="{{ __('PIN') }}" autocomplete="off">
                                <button type="button" class="btn btn-warning pos-btn" @click="approveVoid()" :disabled="! managerId || pin.length < 4 || busy" data-approve>{{ __('Approve') }}</button>
                            </div>
                        @endif
                    </div>

                    <p class="alert alert-danger py-2 mt-2" x-show="error" x-text="error" data-void-error></p>
                    <div class="d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-danger btn-lg pos-btn flex-grow-1" @click="confirmVoid()" :disabled="busy" x-show="! voiding.asking" data-confirm-void><i class="bi bi-x-octagon"></i> {{ __('Void item') }}</button>
                        <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="voiding = null; error = ''">{{ __('Cancel') }}</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Transfer to a free table or merge another open order in. --}}
        <div class="pos-sheet" x-show="tool" x-cloak>
            <div class="pos-sheet__body" role="dialog" aria-modal="true" :aria-label="tool === 'transfer' ? '{{ __('Move to another table') }}' : '{{ __('Merge another order in') }}'" data-tool-sheet>
                <div x-show="tool === 'transfer'">
                    <h2 class="h4">{{ __('Move to another table') }}</h2>
                    <div class="pos-covers mb-3">
                        <template x-for="table in freeTables" :key="table.id">
                            <button type="button" class="btn btn-outline-primary pos-btn" @click="transfer(table.id)" :disabled="busy" :data-transfer-to="table.number" x-text="table.number"></button>
                        </template>
                    </div>
                    <p class="text-body-secondary" x-show="freeTables.length === 0">{{ __('No table is free.') }}</p>
                </div>
                <div x-show="tool === 'merge'">
                    <h2 class="h4">{{ __('Merge another order in') }}</h2>
                    <p class="text-body-secondary">{{ __('Its items move to this order and its table is freed.') }}</p>
                    <div class="d-grid gap-2 mb-3">
                        <template x-for="other in otherOrders" :key="other.id">
                            <button type="button" class="btn btn-outline-primary pos-btn text-start" @click="merge(other.id)" :disabled="busy" :data-merge="other.id" x-text="other.label"></button>
                        </template>
                    </div>
                    <p class="text-body-secondary" x-show="otherOrders.length === 0">{{ __('No other open order.') }}</p>
                </div>
                <p class="alert alert-danger py-2" x-show="error" x-text="error"></p>
                <button type="button" class="btn btn-outline-secondary btn-lg pos-btn" @click="tool = null; error = ''">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</x-layouts::pos>
