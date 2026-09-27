{{-- Row actions in the users DataTable. --}}
@php($status = $user->status)
@can('update', $user)
    <a href="{{ route('iam.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-shield-check"></i> {{ __('Roles') }}</a>
@endcan

@if ($user->is($actor))
    <span class="badge text-bg-secondary">{{ __('You') }}</span>
@elseif ($actor->can('update', $user))
    @if ($status === \Modules\IAM\Enums\UserStatus::Invited)
        <form method="POST" action="{{ route('iam.users.invitation.resend', $user) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-send"></i> {{ __('Resend invitation') }}</button>
        </form>
    @endif

    @if ($status === \Modules\IAM\Enums\UserStatus::Inactive)
        <form method="POST" action="{{ route('iam.users.activate', $user) }}" class="d-inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-person-check"></i> {{ __('Activate') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('iam.users.deactivate', $user) }}" class="d-inline"
              data-confirm="{{ __('Deactivate :name?', ['name' => $user->name]) }}"
              data-confirm-text="{{ __('They will be signed out and cannot sign in until reactivated.') }}"
              data-confirm-variant="danger" data-confirm-button="{{ __('Deactivate') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-person-slash"></i> {{ __('Deactivate') }}</button>
        </form>
    @endif
@endif
