{{-- Session flash messages (success, error, warning, info, status) and a validation summary. --}}
@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $types = ['success' => 'success', 'status' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $icons = ['success' => 'bi-check-circle', 'danger' => 'bi-exclamation-octagon', 'warning' => 'bi-exclamation-triangle', 'info' => 'bi-info-circle'];
@endphp

@foreach ($types as $key => $variant)
    @if (session()->has($key))
        <div class="alert alert-{{ $variant }} alert-dismissible fade show d-flex align-items-start" role="alert">
            <i class="bi {{ $icons[$variant] }} me-2 mt-1"></i>
            <div>{{ __((string) session($key)) }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="alert alert-danger d-flex align-items-start" role="alert">
        <i class="bi bi-exclamation-octagon me-2 mt-1"></i>
        <div>
            {{ trans_choice('{1} Please correct the error below.|[2,*] Please correct the :count errors below.', $errors->count(), ['count' => $errors->count()]) }}
            <ul class="mb-0 mt-1 small">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
