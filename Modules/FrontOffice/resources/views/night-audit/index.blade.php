@php
    $day = \Carbon\CarbonImmutable::parse($date);
    $canRun = auth()->user()?->can('frontoffice.audit.run');
@endphp
<x-layouts::app :title="__('Night audit')" :subtitle="$property->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Front Office') => route('frontoffice.desk'), __('Night audit') => null]">
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Audit of :date', ['date' => $day->format('D d M Y')])" icon="bi-moon-stars" data-audit-date="{{ $date }}">
                @if ($audit && $audit->status !== \Modules\FrontOffice\Enums\NightAuditStatus::Completed)
                    <p class="mb-3">{{ __('Last attempt') }}: <x-status-badge :status="$audit->status" /> {{ $audit->started_at->format('d M H:i') }} · {{ $audit->trigger->label() }}</p>
                @endif

                <ol class="list-group list-group-numbered mb-3" data-audit-checks>
                    <li class="list-group-item">
                        <span class="fw-semibold">{{ __('Departures checked out') }}</span>
                        @if ($blocking === [])
                            <span class="badge text-bg-success ms-1">{{ __('OK') }}</span>
                        @else
                            <span class="badge text-bg-danger ms-1">{{ trans_choice(':count still in house|:count still in house', count($blocking)) }}</span>
                            <ul class="small mt-2 mb-0" data-blocking>
                                @foreach ($departures as $stay)
                                    <li>{{ $blocking[$loop->index] }}
                                        <a href="{{ route('frontoffice.check-out.show', $stay->id) }}">{{ __('Check out') }}</a> ·
                                        <a href="{{ route('frontoffice.stay.show', $stay->id) }}">{{ __('Extend') }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                    <li class="list-group-item">
                        <span class="fw-semibold">{{ __('Room charges') }}</span>
                        <span class="text-body-secondary">
                            @if ($nightly)
                                {{ trans_choice(':count night to post|:count nights to post', $nightsToPost) }} {{ trans_choice('for :count stay in house.|for :count stays in house.', count($inHouse)) }}
                            @else
                                {{ __('Recognised at check-out: nothing is posted tonight.') }}
                            @endif
                        </span>
                    </li>
                    <li class="list-group-item">
                        <span class="fw-semibold">{{ __('No-shows') }}</span>
                        @if ($noShows === [])
                            <span class="text-body-secondary">{{ __('Every arrival is checked in.') }}</span>
                        @else
                            <span class="text-body-secondary">{{ __('These arrivals will be marked as no-shows (no-show fee kept, rooms released from tomorrow):') }}</span>
                            <ul class="small mt-2 mb-0" data-no-shows>
                                @foreach ($noShows as $arrival)
                                    <li><a href="{{ route('reservation.bookings.show', $arrival->id) }}">{{ $arrival->code }}</a> · {{ $arrival->groupName ?? $arrival->guestName }} · {{ implode(', ', $arrival->units) }}
                                        · <x-status-badge :status="$arrival->status" /></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                    <li class="list-group-item"><span class="fw-semibold">{{ __('Expired holds') }}</span> <span class="text-body-secondary">{{ __('Unpaid tentative bookings past their deposit time are released.') }}</span></li>
                    <li class="list-group-item"><span class="fw-semibold">{{ __('Restaurant') }}</span> <span class="text-body-secondary">{{ __('Checked once the restaurant POS is in use.') }}</span></li>
                    <li class="list-group-item"><span class="fw-semibold">{{ __('Package split') }}</span> <span class="text-body-secondary">{{ __('The meal part of package rates is kept apart as F&B revenue.') }}</span></li>
                    <li class="list-group-item"><span class="fw-semibold">{{ __('Daily statistics') }}</span> <span class="text-body-secondary">{{ __('Occupancy, ADR, RevPAR, revenue and takings are saved for the flash report.') }}</span></li>
                    <li class="list-group-item"><span class="fw-semibold">{{ __('Business date') }}</span> <span class="text-body-secondary">{{ __('Moves on to :date.', ['date' => $day->addDay()->format('D d M Y')]) }}</span></li>
                </ol>

                @foreach ($warnings as $warning)
                    <div class="alert alert-warning py-2 small mb-2" data-warning>{{ $warning }}</div>
                @endforeach

                @if ($notPossible)
                    <div class="alert alert-info mb-0" data-not-possible>{{ $notPossible }}</div>
                @elseif ($canRun)
                    <form method="POST" action="{{ route('frontoffice.night-audit.store') }}" data-run-audit
                        data-confirm="{{ __('Run the night audit of :date?', ['date' => $day->format('d M Y')]) }}"
                        data-confirm-text="{{ __('Room charges are posted, no-shows marked and the business date moves on. This cannot be undone.') }}">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $date }}">
                        <button type="submit" class="btn btn-primary" @disabled($blocking !== [])><i class="bi bi-moon-stars"></i> {{ __('Run night audit') }}</button>
                    </form>
                @endif
            </x-card>
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Recent audits')" icon="bi-clock-history" body-class="p-0">
                @forelse ($history as $past)
                    <a href="{{ route('frontoffice.night-audit.show', $past) }}" class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom text-decoration-none text-body" data-history="{{ $past->business_date->toDateString() }}">
                        <span>{{ $past->business_date->format('D d M Y') }} <span class="small text-body-secondary">· {{ $past->trigger->label() }}</span></span>
                        <x-status-badge :status="$past->status" />
                    </a>
                @empty
                    <x-empty-state icon="bi-moon-stars" :title="__('No audits yet')" />
                @endforelse
            </x-card>
        </div>
    </div>
</x-layouts::app>
