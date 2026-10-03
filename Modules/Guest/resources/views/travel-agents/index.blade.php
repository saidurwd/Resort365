<x-layouts::app :title="__('Travel agents')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Travel agents') => null]">
    @can('create', \Modules\Guest\Models\TravelAgent::class)
        <x-slot:actions>
            <a href="{{ route('guest.travel-agents.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New travel agent') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($records->isEmpty())
            <x-empty-state icon="bi-globe2" :title="__('No travel agents yet')" :message="__('Agents that send bookings for a commission.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-travel-agents>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Travel agent') }}</th>
                            <th>{{ __('Contact') }}</th>
                            <th class="text-end">{{ __('Commission') }}</th>
                            <th class="text-end">{{ __('Credit limit (:currency)', ['currency' => $currency]) }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $agent)
                            <tr>
                                <td class="ps-3"><div class="fw-semibold">{{ $agent->name }}</div><div class="small text-body-secondary">{{ $agent->code }}</div></td>
                                <td>{{ $agent->contact_person }}<div class="small text-body-secondary">{{ collect([$agent->phone, $agent->email])->filter()->implode(' · ') }}</div></td>
                                <td class="text-end">{{ rtrim(rtrim($agent->commission_percent, '0'), '.') }}%</td>
                                <td class="text-end font-monospace">{{ number_format((float) $agent->credit_limit, 2) }}</td>
                                <td>@include('guest::partials.active-badge', ['active' => $agent->is_active])</td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $agent)
                                        <a href="{{ route('guest.travel-agents.edit', $agent) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @endcan
                                    @can('delete', $agent)
                                        <x-confirm-delete :action="route('guest.travel-agents.destroy', $agent)" icon-only :title="__('Delete travel agent :name?', ['name' => $agent->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
</x-layouts::app>
