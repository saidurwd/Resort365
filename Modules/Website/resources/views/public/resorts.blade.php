{{-- `/` when a company publishes several resorts (Step 8.2a): a simple list. $sites = published WebsiteSite models. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Our resorts') }}</title>
    @vite(['resources/scss/website.scss', 'resources/js/website.js'])
</head>
<body class="website theme-{{ $sites->first()?->theme->value ?? 'forest' }}">
    <main class="website-section">
        <div class="container website-narrow">
            <h1 class="website-section-title text-center">{{ __('Our resorts') }}</h1>
            <div class="list-group" data-resorts>
                @foreach ($sites as $resort)
                    <a class="list-group-item list-group-item-action py-3" href="{{ route('website.home', ['site' => $resort->slug]) }}">
                        <div class="fw-semibold">{{ $resort->name }}</div>
                        @if ($resort->translated('tagline'))
                            <div class="small text-body-secondary">{{ $resort->translated('tagline') }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </main>
</body>
</html>
