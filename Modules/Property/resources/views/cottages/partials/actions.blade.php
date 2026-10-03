{{-- Row actions in the cottages DataTable. --}}
<a href="{{ route('property.cottages.show', $cottage) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> {{ __('Open') }}</a>
@can('update', $cottage)
    <a href="{{ route('property.cottages.edit', $cottage) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
@endcan
