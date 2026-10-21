{{-- A platform admin is signed in as this user (Step 7.3): a red bar on every page, with the way out. --}}
@if (is_array($state = session('impersonation')))
    <div class="alert alert-danger rounded-0 mb-0 py-2 d-flex flex-wrap align-items-center justify-content-between gap-2" role="alert" data-impersonation-banner>
        <span><i class="bi bi-person-badge me-1"></i>{{ __('You are signed in as :user, as the platform admin :admin. Everything you do is recorded under your name.', ['user' => $state['user_name'], 'admin' => $state['admin_name']]) }}</span>
        <form method="POST" action="{{ route('iam.impersonation.leave') }}">@csrf<button type="submit" class="btn btn-sm btn-light" data-leave-impersonation><i class="bi bi-box-arrow-left"></i> {{ __('Leave') }}</button></form>
    </div>
@endif
