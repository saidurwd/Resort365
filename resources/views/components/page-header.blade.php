@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{--
    Page title with breadcrumbs (label => url, null for the current page) and optional actions.
    <x-page-header :title="__('Rooms')" :breadcrumbs="[__('Setup') => route('setup'), __('Rooms') => null]" />
--}}
<div {{ $attributes->class(['row', 'align-items-center', 'g-2']) }}>
    <div class="col-sm">
        <h3 class="mb-0">{{ $title }}</h3>
        @if ($subtitle)
            <div class="text-body-secondary small">{{ $subtitle }}</div>
        @endif
    </div>

    @if ($breadcrumbs)
        <div class="col-sm-auto">
            <ol class="breadcrumb float-sm-end mb-0">
                @foreach ($breadcrumbs as $label => $url)
                    @if ($url && ! $loop->last)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @else
                        <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                    @endif
                @endforeach
            </ol>
        </div>
    @endif

    @isset($actions)
        <div class="col-12 d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
