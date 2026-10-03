@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{--
    Admin layout (AdminLTE 4).

    <x-layouts::app :title="__('Rooms')" :subtitle="$propertyName" :breadcrumbs="[__('Setup') => null, __('Rooms') => null]">
        <x-slot:actions>…buttons…</x-slot:actions>
        …content…
    </x-layouts::app>

    Pass a `header` slot instead of title/breadcrumbs for a fully custom page header.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        @include('layouts.partials.navbar')
        @include('layouts.partials.sidebar')

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    @if (isset($header))
                        {{ $header }}
                    @elseif ($title)
                        <x-page-header :title="$title" :subtitle="$subtitle" :breadcrumbs="$breadcrumbs">
                            @isset($actions)
                                <x-slot:actions>{{ $actions }}</x-slot:actions>
                            @endisset
                        </x-page-header>
                    @endif
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <x-flash-messages />

                    {{ $slot }}
                </div>
            </div>
        </main>

        @include('layouts.partials.footer')
    </div>

    @stack('scripts')
</body>
</html>
