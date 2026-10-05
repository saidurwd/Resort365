{{--
    The kitchen display (ARCHITECTURE §10.3): New · Preparing · Ready columns of ticket cards. Alpine
    (kdsBoard) holds the board; every tap goes to the server, which answers with the board.
--}}
<x-layouts::kds :title="__('Kitchen display')" :station="$station">
    <div class="kds-board" x-data="kdsBoard(@js($state))" data-kds-board="{{ $station->id }}">
        <template x-teleport="#kds-status">
            <span class="badge kds-bar__live" :class="online ? 'text-bg-success' : 'text-bg-warning'" :data-live="online ? 'live' : 'polling'"
                x-text="online ? '{{ __('Live') }}' : '{{ __('Refreshing every 5 s') }}'"></span>
        </template>
        <template x-teleport="#kds-actions">
            <button type="button" class="btn pos-btn btn-outline-secondary" @click="recall()" :disabled="! board.recall || busy" data-recall>
                <i class="bi bi-arrow-counterclockwise"></i> {{ __('Recall') }} <span x-show="board.recall" x-text="board.recall ? '#' + board.recall.no : ''"></span>
            </button>
        </template>

        <p class="alert alert-danger py-2" x-show="error" x-text="error" x-cloak data-kds-error></p>

        @foreach (['new' => __('New'), 'preparing' => __('Preparing'), 'ready' => __('Ready')] as $status => $heading)
            <section class="kds-column" data-column="{{ $status }}">
                <h2 class="kds-column__title">{{ $heading }} <span class="badge text-bg-secondary" x-text="column('{{ $status }}').length"></span></h2>
                <template x-for="ticket in column('{{ $status }}')" :key="ticket.id">
                    <article class="kds-ticket" :class="[age(ticket), fresh.includes(ticket.id) ? 'kds-ticket--fresh' : '']" :data-ticket="ticket.no" :data-type="ticket.type">
                        <header class="kds-ticket__head">
                            <span class="kds-ticket__no" x-text="'#' + ticket.no"></span>
                            <span class="fw-semibold" x-text="ticket.where"></span>
                            <span class="ms-auto kds-ticket__age" x-text="minutes(ticket) + ' {{ __('min') }}'" data-age></span>
                        </header>
                        <div class="kds-ticket__meta" x-text="ticket.order_no + ' · ' + ticket.waiter"></div>
                        <div class="kds-ticket__void" x-show="ticket.type === 'void'">{{ __('VOID — do not make') }}</div>
                        <ul class="kds-ticket__lines">
                            <template x-for="line in ticket.lines" :key="line.id">
                                <li :class="{ 'kds-line--voided': line.voided }" :data-line="line.name">
                                    <div class="kds-line__item"><span x-text="line.quantity + ' × ' + line.name"></span> <span x-show="line.variant" x-text="'(' + line.variant + ')'"></span></div>
                                    <template x-for="modifier in line.modifiers"><div class="kds-line__modifier" x-text="'+ ' + modifier"></div></template>
                                    <div class="kds-line__note" x-show="line.notes" x-text="line.notes"></div>
                                    <div class="kds-line__allergens" x-show="line.allergens.length" data-allergens>
                                        <i class="bi bi-exclamation-triangle-fill"></i> <span x-text="line.allergens.join(', ')"></span>
                                    </div>
                                    <div class="kds-line__meta">
                                        <span x-text="line.course"></span><span x-show="line.seat" x-text="' · {{ __('Seat') }} ' + line.seat"></span>
                                        <span x-show="line.void_reason" x-text="' · ' + line.void_reason"></span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                        <footer class="kds-ticket__actions">
                            <button type="button" class="btn btn-lg pos-btn flex-grow-1" :class="ticket.type === 'void' ? 'btn-danger' : (ticket.status === 'ready' ? 'btn-success' : 'btn-primary')"
                                @click="act(ticket, nextAction(ticket))" :disabled="busy === ticket.id" x-text="nextLabel(ticket)" :data-act="nextAction(ticket)"></button>
                            <button type="button" class="btn btn-lg pos-btn btn-outline-success" x-show="ticket.type === 'new' && ticket.status === 'new'"
                                @click="act(ticket, 'ready')" :disabled="busy === ticket.id" data-act-ready>{{ __('Ready') }}</button>
                        </footer>
                    </article>
                </template>
            </section>
        @endforeach
    </div>

    <x-slot:status><span id="kds-status"></span></x-slot:status>
    <x-slot:actions>
        <span id="kds-actions"></span>
        <form method="POST" action="{{ route('kds.sign-out') }}" data-kds-sign-out
            @if ($isDevice) data-confirm="{{ __('Sign this screen out of :station? It needs the display token again.', ['station' => $station->name]) }}" @endif>
            @csrf
            <button type="submit" class="btn pos-btn btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> {{ $isDevice ? __('Sign out') : __('Other station') }}</button>
        </form>
    </x-slot:actions>
</x-layouts::kds>
