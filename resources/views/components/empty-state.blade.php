@props([
    'title',
    'message' => null,
    'icon' => 'bi-inbox',
])

{{-- <x-empty-state :title="__('No rooms yet')" :message="__('Add a cottage to get started.')"><a …>…</a></x-empty-state> --}}
<div {{ $attributes->class(['empty-state']) }}>
    <i class="bi {{ $icon }} empty-state-icon" aria-hidden="true"></i>
    <h5 class="mt-3 mb-1">{{ $title }}</h5>
    @if ($message)
        <p class="text-body-secondary mb-3">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
