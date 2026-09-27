{{-- Forbidden: shown for missing permissions (can: middleware, policies) and disabled modules. --}}
<x-layouts::guest :title="__('Access denied')">
    <div class="text-center">
        <i class="bi bi-shield-lock text-danger display-5"></i>
        <h1 class="h4 mt-3">{{ __('You don\'t have access to this page') }}</h1>
        <p class="text-body-secondary">{{ $exception?->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : __('Ask your administrator if you need access.') }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-sm"><i class="bi bi-house"></i> {{ __('Go to the start page') }}</a>
    </div>
</x-layouts::guest>
