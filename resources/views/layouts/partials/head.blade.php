<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
@isset($themeSaveUrl)
    <meta name="theme-save-url" content="{{ $themeSaveUrl }}">
@endisset

<title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

@include('layouts.partials.theme-script')

@fonts
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
@stack('head')
