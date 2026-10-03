<x-layouts::app :title="__('Seasons')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Seasons') => null]">
    @can('create', \Modules\Rates\Models\Season::class)
        <x-slot:actions>
            <a href="{{ route('rates.seasons.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New season') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($seasons->isEmpty())
            <x-empty-state icon="bi-calendar-range" :title="__('No seasons yet')" :message="__('Without seasons, every night uses the base rates.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-seasons>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Season') }}</th>
                            <th class="text-end">{{ __('Priority') }}</th>
                            <th>{{ __('Periods') }}</th>
                            <th class="text-end">{{ __('Rates') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($seasons as $season)
                            <tr>
                                <td class="ps-3"><span class="badge text-bg-{{ $season->color->value }}">{{ $season->name }}</span></td>
                                <td class="text-end">{{ $season->priority }}</td>
                                <td>
                                    @foreach ($season->periods as $period)
                                        <div class="small text-nowrap">{{ $period->start_date->format('d M Y') }} – {{ $period->end_date->format('d M Y') }}</div>
                                    @endforeach
                                </td>
                                <td class="text-end">{{ $season->rates_count }}</td>
                                <td>
                                    @if ($season->is_active)
                                        <span class="badge text-bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $season)
                                        <a href="{{ route('rates.seasons.edit', $season) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $season)
                                        <x-confirm-delete :action="route('rates.seasons.destroy', $season)" icon-only :title="__('Delete season :name?', ['name' => $season->name])"
                                            :text="__('Its rates are deleted too; those nights fall back to the base rates.')" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="small text-body-secondary">{{ __('Where seasons overlap, the higher priority wins.') }}</p>
</x-layouts::app>
