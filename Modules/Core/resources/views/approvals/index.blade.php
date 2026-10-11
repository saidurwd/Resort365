{{--
    Approvals (Step 5.1): what waits for my decision, what I asked for, everything for managers, and my delegation.
--}}
<x-layouts::app :title="__('Approvals')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Approvals') => null]">
    @php($table = fn ($rows, $emptyText, $who = true) => compact('rows', 'emptyText', 'who'))
    <div class="row">
        <div class="col-xl-8">
            @foreach ([['waiting', __('Waiting for my decision'), $waiting, __('Nothing waits for you.')], ['mine', __('Asked by me'), $mine, __('You have not asked for anything.')], ['all', __('All requests'), $all, __('No requests yet.')]] as [$key, $title, $rows, $empty])
                @continue($key === 'all' && $all->isEmpty())
                <x-card :title="$title" icon="bi-check2-square" body-class="p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" data-approvals="{{ $key }}">
                            <thead><tr><th class="ps-3">{{ __('Request') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Asked by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr data-approval="{{ $row->id }}">
                                        <td class="ps-3">{{ $row->reference }}</td>
                                        <td>{{ $types[$row->document_type] ?? $row->document_type }}</td>
                                        <td class="text-end font-monospace">{{ number_format((float) $row->amount, 2) }}</td>
                                        <td>{{ $names[$row->requested_by] ?? '' }}</td>
                                        <td><x-status-badge :status="$row->status" /></td>
                                        <td class="text-end pe-3"><a href="{{ route('core.approvals.show', $row) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ $empty }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>
        <div class="col-xl-4">
            <x-card :title="__('My delegation')" icon="bi-person-lines-fill">
                <p class="small text-body-secondary">{{ __('While away, let a colleague decide the requests that wait for you.') }}</p>
                @foreach ($delegations as $delegation)
                    <div class="d-flex justify-content-between align-items-center mb-2" data-delegation="{{ $delegation->id }}">
                        <span>{{ $names[$delegation->delegate_user_id] ?? '' }} <span class="small text-body-secondary">{{ $delegation->starts_on->format('d M') }} – {{ $delegation->ends_on->format('d M Y') }}</span></span>
                        <form method="POST" action="{{ route('core.approval-delegations.destroy', $delegation) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('End delegation') }}"><i class="bi bi-x-lg"></i></button></form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('core.approval-delegations.store') }}" data-delegation-form>
                    @csrf
                    <x-form.select name="delegate_user_id" :label="__('Colleague')" :options="$colleagues" :value="old('delegate_user_id')" :placeholder="__('Choose a colleague')" required />
                    <div class="row">
                        <div class="col-6"><x-form.date name="starts_on" :label="__('From')" :value="old('starts_on', now()->toDateString())" required /></div>
                        <div class="col-6"><x-form.date name="ends_on" :label="__('Until')" :value="old('ends_on', now()->addWeek()->toDateString())" required /></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Delegate') }}</button>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts::app>
