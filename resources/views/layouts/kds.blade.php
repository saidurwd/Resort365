@props([
    'title' => null,
    'station' => null,
])

{{--
    Kitchen display layout (ARCHITECTURE §10.1): a full-screen ticket board for wall-mounted screens,
    dark and readable from a distance, with no idle sign-out.

    <x-layouts::kds :title="__('Kitchen display')" :station="$station">…</x-layouts::kds>
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    @include('layouts.partials.head', ['title' => $title])
    <script>
        // Kitchens default to dark; a screen may keep light (kds-theme in localStorage).
        try {
            document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('kds-theme') || 'dark');
        } catch (e) {}
    </script>
</head>
<body class="kds" data-theme-key="kds-theme" data-theme-default="dark"
    x-data="{ dark: (() => { try { return (localStorage.getItem('kds-theme') || 'dark') === 'dark' } catch (e) { return true } })() }">
    <header class="kds-bar">
        <span class="kds-bar__brand"><i class="bi bi-fire"></i> {{ $station?->name ?? __('Kitchen display') }}</span>
        @if ($station)
            <span class="kds-bar__meta">{{ $station->outlet->name }}</span>
        @endif
        {{ $status ?? '' }}
        <span class="ms-auto"></span>
        <button type="button" class="btn pos-btn btn-outline-secondary" aria-label="{{ __('Light or dark') }}"
            @click="dark = ! dark; document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light'); try { localStorage.setItem('kds-theme', dark ? 'dark' : 'light') } catch (e) {}">
            <i class="bi" :class="dark ? 'bi-sun' : 'bi-moon'"></i>
        </button>
        {{ $actions ?? '' }}
    </header>

    <main class="kds-main">
        <x-flash-messages />
        {{ $slot }}
    </main>
</body>
</html>
