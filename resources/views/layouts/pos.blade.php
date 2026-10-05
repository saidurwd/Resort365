@props([
    'title' => null,
    'terminal' => null,
])

{{--
    POS layout (ARCHITECTURE §10.1, AD-15): full screen for touch tablets on the service floor, no
    sidebar, touch targets of at least 48 px, light or dark per device (bars).

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
<body class="pos" x-data="{ dark: document.documentElement.getAttribute('data-bs-theme') === 'dark' }">
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
</body>
</html>
