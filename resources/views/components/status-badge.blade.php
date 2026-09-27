@props([
    'status',
])

{{-- Badge for any enum implementing App\Support\Enums\HasLabelAndColor: <x-status-badge :status="$reservation->status" /> --}}
@php
    /** @var \App\Support\Enums\HasLabelAndColor $status */
@endphp
<span {{ $attributes->class(['badge', 'text-bg-'.$status->color()]) }}>{{ $status->label() }}</span>
