@props([
    'title' => null,
    'icon' => null,
    'collapsible' => false,
    'collapsed' => false,
    'variant' => null,
    'bodyClass' => null,
])

{{--
    <x-card :title="__('Rooms')" icon="bi-door-open" variant="primary">
        <x-slot:tools>…small buttons…</x-slot:tools>
        …body…
        <x-slot:footer>…</x-slot:footer>
    </x-card>
    `variant` adds an AdminLTE outline colour (card-outline card-{variant}).
--}}
<div {{ $attributes->class(['card', 'mb-4', 'card-outline card-'.$variant => $variant, 'collapsed-card' => $collapsed]) }}>
    @if ($title || isset($tools) || $collapsible)
        <div class="card-header">
            @if ($title)
                <h3 class="card-title">
                    @if ($icon)<i class="bi {{ $icon }} me-1"></i>@endif
                    {{ $title }}
                </h3>
            @endif

            @if (isset($tools) || $collapsible)
                <div class="card-tools">
                    {{ $tools ?? '' }}
                    @if ($collapsible)
                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" aria-label="{{ __('Collapse') }}">
                            <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
                            <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif

    <div @class(['card-body', $bodyClass])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer">{{ $footer }}</div>
    @endisset
</div>
