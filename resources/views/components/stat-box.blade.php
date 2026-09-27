@props([
    'value',
    'label',
    'icon' => null,
    'color' => 'primary',
    'url' => null,
    'linkText' => null,
])

{{-- KPI tile (AdminLTE small box): <x-stat-box :value="$occupancy.'%'" :label="__('Occupancy')" icon="bi-house-check" color="success" /> --}}
<div {{ $attributes->class(['small-box', 'text-bg-'.$color]) }}>
    <div class="inner">
        <h3>{{ $value }}</h3>
        <p>{{ $label }}</p>
    </div>
    @if ($icon)
        <i class="small-box-icon bi {{ $icon }}" aria-hidden="true"></i>
    @endif
    @if ($url)
        <a href="{{ $url }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
            {{ $linkText ?? __('More info') }} <i class="bi bi-arrow-right-circle"></i>
        </a>
    @endif
</div>
