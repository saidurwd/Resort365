<x-layouts::app :title="__('Financial reports')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Financial reports') => null]">
    <div class="row" data-reports>
        @foreach ($reports as $key => $title)
            <div class="col-md-6 col-xl-3 mb-3">
                <a href="{{ route('accounting.reports.show', $key) }}" class="card h-100 text-decoration-none" data-report="{{ $key }}">
                    <div class="card-body"><h2 class="h6 mb-1">{{ __($title) }}</h2></div>
                </a>
            </div>
        @endforeach
    </div>
</x-layouts::app>
