@if ($active)
    <span class="badge text-bg-success">{{ __('Active') }}</span>
@else
    <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
@endif
