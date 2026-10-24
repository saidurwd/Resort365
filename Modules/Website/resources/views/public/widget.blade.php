{{-- The embeddable search box (Step 8.2b): shown in an iframe on the resort's own website; a search opens this site in the whole window. --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $site->name }}</title>
    @vite(['resources/scss/website.scss', 'resources/js/website.js'])
</head>
<body class="website website-widget theme-{{ $site->theme->value }}">
    <div class="website-widget-title">{{ __('Book your stay at :name', ['name' => $site->name]) }}</div>
    @include('website::public.partials.search', ['compact' => true, 'target' => '_top'])
</body>
</html>
