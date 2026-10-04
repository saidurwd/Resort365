@php
    $title = __('Flash report :date', ['date' => $date->format('D d M Y')]);
@endphp
@if ($print && $report)
    <x-layouts::print :title="$title">
        <h1 class="h4">{{ $property?->name }}</h1>
        <p class="fw-semibold">{{ $title }}</p>
        @include('frontoffice::reports.partials.flash-body')
    </x-layouts::print>
@else
    <x-layouts::app :title="__('Flash report')" :subtitle="$property?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Front Office') => route('frontoffice.desk'), __('Flash report') => null]">
        <x-slot:actions>
            @if ($report)
                <a href="{{ route('frontoffice.reports.flash', ['date' => $date->toDateString(), 'print' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> {{ __('Print') }}</a>
            @endif
        </x-slot:actions>

        <div class="d-flex flex-wrap gap-2 align-items-center mb-3" data-flash-nav>
            <div class="btn-group">
                <a href="{{ route('frontoffice.reports.flash', ['date' => $date->subDay()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> {{ __('Previous day') }}</a>
                <a href="{{ route('frontoffice.reports.flash', ['date' => $businessDate]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Today (open)') }}</a>
                <a href="{{ route('frontoffice.reports.flash', ['date' => $date->addDay()->toDateString()]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Next day') }} <i class="bi bi-chevron-right"></i></a>
            </div>
            <span class="fw-semibold ms-1">{{ $date->format('D d M Y') }}</span>
        </div>

        @if ($report)
            @include('frontoffice::reports.partials.flash-body')
        @else
            <x-card>
                <x-empty-state icon="bi-graph-up" :title="__('No report for this date')" :message="__('A day has a flash report once its night audit has run.')" />
            </x-card>
        @endif
    </x-layouts::app>
@endif
