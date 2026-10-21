{{--
    A reminder on the dashboard (Step 7.4) while a new company has setup steps left and has not put the checklist away. Shown only to
    people who may do the setup.
--}}
@auth('web')
    @can('core.onboarding.manage')
        @php($progress = app(\Modules\Core\Contracts\Onboarding::class)->progress())
        @if ($progress->pending())
            <div class="alert alert-info d-flex justify-content-between align-items-center gap-3 mb-3" role="status" data-onboarding-banner>
                <span><i class="bi bi-list-check"></i> {{ __('Finish setting up your resort: :done of :total steps done.', ['done' => $progress->doneCount(), 'total' => $progress->total()]) }}</span>
                <a href="{{ route('core.onboarding.index') }}" class="btn btn-sm btn-info">{{ __('Continue') }}</a>
            </div>
        @endif
    @endcan
@endauth
