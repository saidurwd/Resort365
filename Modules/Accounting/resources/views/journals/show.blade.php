@php
    use Modules\Accounting\Enums\JournalStatus;
    $money = fn ($amount): string => (float) $amount === 0.0 ? '' : number_format((float) $amount, 2);
@endphp
<x-layouts::app :title="$entry->entry_no ?? __('Draft entry')" :subtitle="$entry->description" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Journal entries') => route('accounting.journals.index'), ($entry->entry_no ?? __('Draft')) => null]">
    <x-slot:actions>
        @if ($entry->isDraft())
            @can('update', $entry)<a href="{{ route('accounting.journals.edit', $entry) }}" class="btn btn-outline-secondary" data-edit-draft><i class="bi bi-pencil"></i> {{ __('Change') }}</a>@endcan
            @can('post', $entry)
                <form method="POST" action="{{ route('accounting.journals.post', $entry) }}" class="d-inline" data-confirm="{{ __('Post this entry? It cannot be changed afterwards, only reversed.') }}">@csrf
                    <button type="submit" class="btn btn-success" data-post><i class="bi bi-check2-circle"></i> {{ __('Post') }}</button></form>
            @endcan
            @can('update', $entry)
                <form method="POST" action="{{ route('accounting.journals.destroy', $entry) }}" class="d-inline" data-confirm="{{ __('Discard this draft?') }}" data-confirm-variant="danger">@csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger" data-discard><i class="bi bi-trash"></i> {{ __('Discard') }}</button></form>
            @endcan
        @elseif ($entry->status === JournalStatus::Posted && $entry->reverses_id === null)
            @can('reverse', $entry)
                <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reverse-modal" data-reverse><i class="bi bi-arrow-counterclockwise"></i> {{ __('Reverse') }}</button>
            @endcan
        @endif
    </x-slot:actions>

    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Lines')" icon="bi-list-ul" body-class="p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" data-entry-lines>
                        <thead><tr><th class="ps-3">{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }}</th><th class="text-end">{{ __('Credit') }}</th><th class="pe-3">{{ __('Memo and dimensions') }}</th></tr></thead>
                        <tbody>
                            @foreach ($entry->lines as $line)
                                <tr>
                                    <td class="ps-3">{{ $line->account->label() }}</td>
                                    <td class="text-end font-monospace">{{ $money($line->debit) }}</td>
                                    <td class="text-end font-monospace">{{ $money($line->credit) }}</td>
                                    <td class="small pe-3">
                                        {{ $line->description }}
                                        @if ($line->property_id)<span class="badge text-bg-light border">{{ $properties[$line->property_id] ?? '#'.$line->property_id }}</span>@endif
                                        @if ($line->department_id)<span class="badge text-bg-light border">{{ $departments[$line->department_id] ?? '#'.$line->department_id }}</span>@endif
                                        @if ($line->party_type)<span class="badge text-bg-light border">{{ $line->party_type->label() }} #{{ $line->party_id }}</span>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr class="fw-bold"><td class="ps-3">{{ __('Total') }}</td><td class="text-end font-monospace" data-total-debit>{{ number_format((float) $entry->lines->sum('debit'), 2) }}</td><td class="text-end font-monospace" data-total-credit>{{ number_format((float) $entry->lines->sum('credit'), 2) }}</td><td></td></tr></tfoot>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card :title="__('Entry')" icon="bi-journal-text">
                <dl class="row mb-0" data-entry-summary>
                    <dt class="col-5">{{ __('Number') }}</dt><dd class="col-7">{{ $entry->entry_no ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Status') }}</dt><dd class="col-7"><x-status-badge :status="$entry->status" /></dd>
                    <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $entry->entry_date->format('d M Y') }}</dd>
                    <dt class="col-5">{{ __('Period') }}</dt><dd class="col-7">{{ $entry->period?->name ?? '—' }}</dd>
                    <dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $entry->reference ?? '—' }}</dd>
                    @if ($entry->posted_at)<dt class="col-5">{{ __('Posted') }}</dt><dd class="col-7">{{ $names[$entry->posted_by] ?? '' }} · {{ $entry->posted_at->inPropertyTime()->format('d M Y H:i') }}</dd>@endif
                    @if ($entry->reverses)<dt class="col-5">{{ __('Reverses') }}</dt><dd class="col-7"><a href="{{ route('accounting.journals.show', $entry->reverses) }}">{{ $entry->reverses->entry_no }}</a></dd>@endif
                    @if ($entry->reversedBy)<dt class="col-5">{{ __('Reversed by') }}</dt><dd class="col-7"><a href="{{ route('accounting.journals.show', $entry->reversedBy) }}">{{ $entry->reversedBy->entry_no }}</a></dd>@endif
                    @if ($entry->source_type)<dt class="col-5">{{ __('Source') }}</dt><dd class="col-7">{{ $entry->source_type }} #{{ $entry->source_id }} · {{ $entry->source_event }}</dd>@endif
                </dl>
            </x-card>
            @if ($sources !== [])
                <x-card :title="__('Source documents')" icon="bi-link-45deg" body-class="p-0">
                    <ul class="list-group list-group-flush" data-sources>
                        @foreach ($sources as $source)
                            <li class="list-group-item"><a href="{{ $source['url'] }}" data-source>{{ $source['label'] }}</a>@if ($source['detail']) <span class="text-body-secondary small">{{ $source['detail'] }}</span>@endif</li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
            <x-audit-trail :entries="$trail" />
        </div>
    </div>

    @can('reverse', $entry)
        <x-modal id="reverse-modal" :title="__('Reverse :no', ['no' => $entry->entry_no])" :show="$errors->has('reason')">
            <form method="POST" action="{{ route('accounting.journals.reverse', $entry) }}" data-reverse-form>
                @csrf
                <p class="text-body-secondary">{{ __('A new entry with every debit and credit swapped is posted, and this one is marked reversed.') }}</p>
                <x-form.input name="date" type="date" :label="__('Date of the reversal')" :value="now()->toDateString()" />
                <x-form.input name="reason" :label="__('Reason')" required />
                <button type="submit" class="btn btn-warning">{{ __('Reverse entry') }}</button>
            </form>
        </x-modal>
    @endcan
</x-layouts::app>
