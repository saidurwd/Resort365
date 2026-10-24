{{--
    The public website's page (Step 8.2a): `@extends('website::public.layout')` with sections `title`, `description` and `content`.
    Shared by the middleware: $site, $preview, $locales; from the controller: $property, $currency, $hasDining.
--}}
@php
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle !== '' ? $pageTitle.' · '.$site->name : ($site->translated('seo_title') ?: $site->name);
    $description = trim($__env->yieldContent('description')) ?: ($site->translated('seo_description') ?: $site->translated('tagline'));
    $nav = [
        'website.rooms' => __('Rooms & cottages'),
        'website.dining' => $hasDining ? __('Dining') : null,
        'website.offers' => __('Offers'),
        'website.gallery' => __('Gallery'),
        'website.contact' => __('Contact'),
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    @if ($description)
        <meta name="description" content="{{ \Illuminate\Support\Str::limit($description, 160) }}">
    @endif
    @if ($preview)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    @foreach ($locales as $code => $language)
        <link rel="alternate" hreflang="{{ $code }}" href="{{ url()->current() }}?lang={{ $code }}">
    @endforeach
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    @vite(['resources/scss/website.scss', 'resources/js/website.js'])
</head>
<body class="website theme-{{ $site->theme->value }}">
    @if ($preview)
        <div class="website-preview-bar" data-preview>
            <i class="bi bi-eye"></i> {{ __('Preview: this website is not published yet.') }}
        </div>
    @endif

    <header class="website-header sticky-top">
        <nav class="navbar navbar-expand-lg" aria-label="{{ __('Main menu') }}">
            <div class="container">
                <a class="navbar-brand website-brand" href="{{ route('website.home', ['site' => $site->slug]) }}">
                    <span class="website-brand-name">{{ $site->name }}</span>
                    @if ($site->translated('tagline'))
                        <span class="website-brand-tagline d-none d-md-block">{{ $site->translated('tagline') }}</span>
                    @endif
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#website-menu" aria-controls="website-menu" aria-expanded="false" aria-label="{{ __('Menu') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="website-menu">
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        @foreach (array_filter($nav) as $route => $label)
                            <li class="nav-item">
                                <a @class(['nav-link', 'active' => request()->routeIs($route.'*')]) href="{{ route($route, ['site' => $site->slug]) }}">{{ $label }}</a>
                            </li>
                        @endforeach
                        <li class="nav-item dropdown" data-language-switch>
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-translate"></i> {{ $locales[app()->getLocale()] ?? 'English' }}
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @foreach ($locales as $code => $language)
                                    <li><a @class(['dropdown-item', 'active' => app()->getLocale() === $code]) href="{{ url()->current() }}?lang={{ $code }}" hreflang="{{ $code }}" lang="{{ $code }}">{{ $language }}</a></li>
                                @endforeach
                            </ul>
                        </li>
                        @if ($site->phone)
                            <li class="nav-item ms-lg-2">
                                <a class="btn btn-website-primary" href="tel:{{ preg_replace('/[^\d+]/', '', $site->phone) }}"><i class="bi bi-telephone"></i> {{ __('Call to book') }}</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="website-main">
        @yield('content')
    </main>

    <footer class="website-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-5">
                    <div class="website-footer-name">{{ $site->name }}</div>
                    @if ($site->address ?? $property?->address)
                        <p class="mb-1"><i class="bi bi-geo-alt"></i> {{ $site->address ?? $property?->address }}</p>
                    @endif
                    @if ($site->phone)
                        <p class="mb-1"><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^\d+]/', '', $site->phone) }}">{{ $site->phone }}</a></p>
                    @endif
                    @if ($site->email)
                        <p class="mb-1"><i class="bi bi-envelope"></i> <a href="mailto:{{ $site->email }}">{{ $site->email }}</a></p>
                    @endif
                </div>
                <div class="col-6 col-md-4">
                    <ul class="list-unstyled mb-0">
                        @foreach (array_filter($nav) as $route => $label)
                            <li><a href="{{ route($route, ['site' => $site->slug]) }}">{{ $label }}</a></li>
                        @endforeach
                        <li><a href="{{ route('website.policies', ['site' => $site->slug]) }}">{{ __('Policies') }}</a></li>
                    </ul>
                </div>
                <div class="col-6 col-md-3 text-md-end">
                    @if ($site->facebook_url)
                        <a class="website-social" href="{{ $site->facebook_url }}" rel="noopener" target="_blank" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    @endif
                    @if ($site->instagram_url)
                        <a class="website-social" href="{{ $site->instagram_url }}" rel="noopener" target="_blank" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    @endif
                    @if ($site->whatsapp)
                        <a class="website-social" href="https://wa.me/{{ preg_replace('/\D/', '', $site->whatsapp) }}" rel="noopener" target="_blank" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    @endif
                </div>
            </div>
            <div class="website-footer-credit">© {{ now()->year }} {{ $site->name }} · {{ __('Powered by Resort365') }}</div>
        </div>
    </footer>
</body>
</html>
