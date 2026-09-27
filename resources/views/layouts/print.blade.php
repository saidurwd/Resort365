@props([
    'title' => null,
])

{{-- A4 document layout (invoices, vouchers, payslips). No app chrome; the toolbar is hidden when printing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @fonts
    @vite('resources/scss/print.scss')
</head>
<body class="print-document">
    <div class="print-toolbar d-flex justify-content-end gap-2">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> {{ __('Back') }}
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> {{ __('Print') }}
        </button>
    </div>

    <main class="print-sheet">
        {{ $slot }}
    </main>
</body>
</html>
