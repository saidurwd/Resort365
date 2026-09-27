@props([
    'title' => null,
])

{{-- Centred card for sign-in, password reset and similar pages. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => $title])
</head>
<body class="login-page bg-body-secondary">
    <div class="login-box">
        <div class="login-logo">
            <a href="{{ url('/') }}" class="link-body-emphasis text-decoration-none">
                <i class="bi bi-tree-fill text-success"></i>
                <b>{{ config('app.name') }}</b>
            </a>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <x-flash-messages />

                {{ $slot }}
            </div>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
