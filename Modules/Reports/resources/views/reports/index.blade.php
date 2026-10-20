<x-layouts::app :title="__('Reports')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Reports') => null]">
    @if ($canExport)
        <x-slot:actions><a href="{{ route('reports.exports.index') }}" class="btn btn-sm btn-outline-secondary" data-my-files><i class="bi bi-download"></i> {{ __('My files') }}</a></x-slot:actions>
    @endif

    @forelse ($groups as $group => $definitions)
        <h2 class="h5 mt-3">{{ $group === 'rooms' ? __('Rooms') : __('Guests') }}</h2>
        <div class="row g-3" data-report-group="{{ $group }}">
            @foreach ($definitions as $definition)
                <div class="col-md-6 col-xl-4">
                    <x-card :title="$definition->title">
                        <p class="text-body-secondary small">{{ $definition->description }}</p>
                        <a href="{{ route('reports.show', $definition->key) }}" class="btn btn-sm btn-primary" data-report="{{ $definition->key }}">{{ __('Open') }}</a>
                    </x-card>
                </div>
            @endforeach
        </div>
    @empty
        <x-empty-state :title="__('No reports for you here')" :message="__('The reports of the other modules are listed below, as far as your role allows.')" />
    @endforelse

    @if ($links !== [])
        <h2 class="h5 mt-4">{{ __('Reports in other modules') }}</h2>
        <div class="row g-3" data-report-links>
            @foreach ($links as $group => $items)
                <div class="col-md-6 col-xl-4">
                    <x-card :title="__($group)">
                        <ul class="list-unstyled mb-0">
                            @foreach ($items as $item)
                                <li><a href="{{ $item['url'] }}">{{ __($item['label']) }}</a></li>
                            @endforeach
                        </ul>
                    </x-card>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts::app>
