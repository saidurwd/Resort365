<x-layouts::app :title="__('Holidays')" :subtitle="$subtitle" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('HR') => null, __('Holidays') => null]">
    <x-slot:actions>
        @if ($canManage)<a href="{{ route('hr.holidays.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New holiday') }}</a>@endif
    </x-slot:actions>
    <ul class="nav nav-pills mb-3" data-holiday-years>
        @foreach ([$year - 1, $year, $year + 1] as $option)
            <li class="nav-item"><a @class(['nav-link', 'active' => $option === $year]) href="{{ route('hr.holidays.index', ['year' => $option]) }}">{{ $option }}</a></li>
        @endforeach
    </ul>
    <x-card body-class="p-0">
        <table class="table table-sm align-middle mb-0" data-holidays>
            <thead><tr><th class="ps-3">{{ __('Date') }}</th><th>{{ __('Holiday') }}</th><th>{{ __('Pay') }}</th><th>{{ __('Repeats') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($holidays as $date => $holiday)
                    <tr data-holiday="{{ $date }}">
                        <td class="ps-3">{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M Y') }}</td>
                        <td>{{ $holiday->name }}</td>
                        <td>{{ $holiday->is_paid ? __('Paid') : __('Unpaid') }}</td>
                        <td>{{ $holiday->repeats_yearly ? __('Every year') : '—' }}</td>
                        @if ($canManage)
                            <td class="text-end pe-3 text-nowrap">
                                <a href="{{ route('hr.holidays.edit', $holiday) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                <x-confirm-delete :action="route('hr.holidays.destroy', $holiday)" :text="__('":name" will be removed.', ['name' => $holiday->name])" />
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state :title="__('No holidays in :year', ['year' => $year])" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</x-layouts::app>
