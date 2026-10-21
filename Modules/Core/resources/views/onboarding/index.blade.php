<x-layouts::app :title="__('Set up your resort')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Setup checklist') => null]">
    <x-slot:actions>
        @if (! $progress->dismissed)
            <form method="POST" action="{{ route('core.onboarding.dismiss') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Put the checklist away') }}</button>
            </form>
        @endif
    </x-slot:actions>

    <x-card :title="__(':done of :total steps done', ['done' => $progress->doneCount(), 'total' => $progress->total()])" icon="bi-list-check">
        <progress class="w-100 mb-3" max="{{ max(1, $progress->total()) }}" value="{{ $progress->doneCount() }}" aria-label="{{ __('Setup progress') }}"></progress>
        <p class="text-body-secondary">{{ __('Roles, document numbers, the chart of accounts and the other defaults are already in place. These steps are what is specific to your resort.') }}</p>

        <ol class="list-group list-group-numbered">
            @foreach ($progress->steps as $step)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div class="ms-2 me-auto">
                        <div class="fw-semibold">{{ $step['label'] }}</div>
                        <div class="small text-body-secondary">{{ $step['description'] }}</div>
                    </div>
                    @if ($step['done'])
                        <span class="badge text-bg-success"><i class="bi bi-check2"></i> {{ __('Done') }}</span>
                    @else
                        <a href="{{ route($step['route']) }}" class="btn btn-sm btn-primary">{{ __('Start') }}</a>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($progress->complete())
            <div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle"></i> {{ __('Your resort is ready. Make your first booking from Reservations → New booking.') }}</div>
        @endif
    </x-card>
</x-layouts::app>
