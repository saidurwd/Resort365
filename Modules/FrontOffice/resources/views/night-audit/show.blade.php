@php
    $labels = [
        'verify' => __('Checks'), 'post_room_charges' => __('Room charges'), 'no_shows' => __('No-shows'), 'expired_holds' => __('Expired holds'),
        'restaurant' => __('Restaurant'), 'package_split' => __('Package split'), 'statistics' => __('Daily statistics'), 'business_date' => __('Business date'),
    ];
@endphp
<x-layouts::app :title="__('Night audit of :date', ['date' => $audit->business_date->format('d M Y')])"
    :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Night audit') => route('frontoffice.night-audit.index'), $audit->business_date->format('d M Y') => null]">
    <x-slot:actions>
        @can('frontoffice.report.view')
            @if ($audit->status === \Modules\FrontOffice\Enums\NightAuditStatus::Completed)
                <a href="{{ route('frontoffice.reports.flash', ['date' => $audit->business_date->toDateString()]) }}" class="btn btn-outline-primary"><i class="bi bi-graph-up"></i> {{ __('Flash report') }}</a>
            @endif
        @endcan
    </x-slot:actions>

    <x-card>
        <p class="mb-3" data-audit-status="{{ $audit->status->value }}">
            <x-status-badge :status="$audit->status" /> {{ $audit->trigger->label() }} · {{ __('started :time', ['time' => $audit->started_at->inPropertyTime()->format('d M H:i')]) }}
            @if ($audit->completed_at) · {{ __('done :time', ['time' => $audit->completed_at->inPropertyTime()->format('H:i')]) }}@endif
        </p>
        @if ($audit->error)
            <div class="alert alert-danger">{{ $audit->error }}</div>
        @endif
        @foreach ($audit->issues ?? [] as $issue)
            <div class="alert alert-warning py-2">{{ $issue }}</div>
        @endforeach
        @if ($audit->steps)
            <table class="table mb-0">
                <tbody>
                    @foreach ($audit->steps as $step)
                        <tr data-step="{{ $step['step'] }}"><th class="w-25">{{ $labels[$step['step']] ?? $step['step'] }}</th><td>{{ $step['result'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</x-layouts::app>
