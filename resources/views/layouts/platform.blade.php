@props([
    'title' => null,
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{--
    The platform console layout (Step 7.3, AdminLTE 4): for the platform admins' pages on the central domain. It has its own small
    menu; nothing here depends on a tenant.

    <x-layouts::platform :title="__('Plans')" :breadcrumbs="[__('Plans') => null]">
        <x-slot:actions>…buttons…</x-slot:actions>
        …content…
    </x-layouts::platform>
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
        <nav class="app-header navbar navbar-expand bg-body">
            <div class="container-fluid">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="{{ __('Toggle sidebar') }}"><i class="bi bi-list"></i></a></li>
                    <li class="nav-item d-none d-md-flex align-items-center"><span class="badge text-bg-dark"><i class="bi bi-shield-lock me-1"></i>{{ __('Platform console') }}</span></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center me-3"><span class="small text-body-secondary" data-platform-admin>{{ auth('platform')->user()?->name }}</span></li>
                    <li class="nav-item">
                        <form method="POST" action="{{ route('platform.logout') }}">@csrf<button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right"></i> {{ __('Sign out') }}</button></form>
                    </li>
                </ul>
            </div>
        </nav>

        <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
            <div class="sidebar-brand">
                <a href="{{ route('platform.console') }}" class="brand-link"><i class="bi bi-tree-fill brand-image text-success fs-4 lh-1"></i><span class="brand-text fw-semibold">{{ config('app.name') }}</span></a>
            </div>
            <div class="sidebar-wrapper">
                <nav class="mt-2">
                    <ul class="nav sidebar-menu flex-column" role="navigation" aria-label="{{ __('Console navigation') }}">
                        @foreach ([
                            ['platform.console', 'bi-speedometer2', __('Overview'), 'platform.console'],
                            ['platform.tenants.index', 'bi-buildings', __('Tenants'), 'platform.tenants.*'],
                            ['platform.plans.index', 'bi-box-seam', __('Plans'), 'platform.plans.*'],
                            ['platform.announcements.index', 'bi-megaphone', __('Announcements'), 'platform.announcements.*'],
                            ['platform.audit.index', 'bi-journal-text', __('Audit log'), 'platform.audit.*'],
                        ] as [$route, $icon, $label, $active])
                            <li class="nav-item"><a href="{{ route($route) }}" @class(['nav-link', 'active' => request()->routeIs($active)])><i class="nav-icon bi {{ $icon }}"></i><p>{{ $label }}</p></a></li>
                        @endforeach
                    </ul>
                </nav>
            </div>
        </aside>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    @if ($title)
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
    </div>

    @stack('scripts')
</body>
</html>
