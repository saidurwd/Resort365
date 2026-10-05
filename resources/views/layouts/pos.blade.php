@props([
    'title' => null,
    'terminal' => null,
])

{{--
    POS layout (ARCHITECTURE §10.1, AD-15): full screen for touch tablets on the service floor, no
    sidebar, touch targets of at least 48 px, light or dark per device (bars). Staff who take orders see
    the kitchen's "ready to serve" notices here (Step 3.5).

    <x-layouts::pos :title="__('POS')" :terminal="$terminal">…</x-layouts::pos>
--}}
@php
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
    <script>
        // A POS device keeps its own colour mode (dark for bars), separate from the admin's.
        try {
            const mode = localStorage.getItem('pos-theme');
            if (mode) { document.documentElement.setAttribute('data-bs-theme', mode); }
        } catch (e) {}
    </script>
</head>
<body class="pos" data-theme-key="pos-theme" data-theme-default="light"
    x-data="{ dark: (() => { try { return (localStorage.getItem('pos-theme') || 'light') === 'dark' } catch (e) { return false } })() }">
    <header class="pos-bar">
        <span class="pos-bar__brand"><i class="bi bi-cup-hot"></i> {{ $terminal?->outlet->name ?? config('app.name') }}</span>
        @if ($terminal)
            <span class="pos-bar__meta"><i class="bi bi-tablet"></i> {{ $terminal->name }}</span>
        @endif
        {{ $status ?? '' }}
        <span class="ms-auto"></span>
        <button type="button" class="btn pos-btn btn-outline-secondary" aria-label="{{ __('Light or dark') }}"
            @click="dark = ! dark; document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light'); try { localStorage.setItem('pos-theme', dark ? 'dark' : 'light') } catch (e) {}">
            <i class="bi" :class="dark ? 'bi-sun' : 'bi-moon'"></i>
        </button>
        @if ($user && $terminal)
            <span class="pos-bar__user"><i class="bi bi-person-circle"></i> {{ $user->name }}</span>
            <form method="POST" action="{{ route('pos.lock') }}" data-lock-form>
                @csrf
                <button type="submit" class="btn pos-btn btn-primary" data-switch-user><i class="bi bi-people"></i> {{ __('Switch user') }}</button>
            </form>
        @endif
    </header>

    <main class="pos-main">
        <x-flash-messages />
        {{ $slot }}
    </main>

    @if ($user && $terminal && $user->can('restaurant.order.take'))
        {{-- "Ready to serve" and 86 notices from the kitchen (Step 3.5). --}}
        <div class="pos-notices" aria-live="polite" x-data="posReady(@js([
            'channel' => \Modules\Restaurant\Broadcasting\RestaurantChannels::outlet($terminal->tenant_id, $terminal->outlet_id),
            'urls' => ['ready' => route('pos.ready'), 'order' => route('pos.orders.show', ['order' => '__ORDER__'])],
            'userId' => $user->getAuthIdentifier(),
            'labels' => ['table' => __('Table'), 'ready' => __('ready to serve')],
        ]))">
            <template x-for="notice in notices" :key="notice.key">
                <div class="pos-notice" :class="{ 'pos-notice--mine': notice.mine }" role="status" data-notice>
                    <i class="bi" :class="notice.icon"></i>
                    <a :href="notice.url" class="pos-notice__text" x-show="notice.url">
                        <span class="fw-semibold" x-text="notice.title"></span><span class="d-block small" x-text="notice.body"></span>
                    </a>
                    <span class="pos-notice__text fw-semibold" x-show="! notice.url" x-text="notice.title"></span>
                    <button type="button" class="btn-close" @click="dismiss(notice.key)" aria-label="{{ __('Close') }}"></button>
                </div>
            </template>
        </div>
    @endif
</body>
</html>
