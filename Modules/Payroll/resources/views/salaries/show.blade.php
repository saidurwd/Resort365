<x-layouts::app :title="$person->name" :subtitle="$person->employeeNo.' · '.$person->designation" :breadcrumbs="[__('Payroll') => null, __('Salaries') => route('payroll.salaries.index'), $person->name => null]">
    <x-slot:actions><a href="{{ route('hr.employees.show', $person->id) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person"></i> {{ __('Employee record') }}</a></x-slot:actions>
    <div class="row">
        <div class="col-xl-6">
            <x-card :title="__('Pay on :date', ['date' => \Carbon\CarbonImmutable::parse($date)->format('d M Y')])" icon="bi-cash-stack">
                <form method="GET" action="{{ route('payroll.salaries.show', $person->id) }}" class="d-flex gap-2 mb-3">
                    <x-form.date name="date" :value="$date" wrapper-class="mb-0" />
                    <button type="submit" class="btn btn-outline-secondary">{{ __('Show') }}</button>
                </form>
                @if ($error)
                    <div class="alert alert-danger" data-preview-error>{{ $error }}</div>
                @elseif ($breakdown)
                    <p class="small text-body-secondary">{{ $current->structure->name }} · {{ __('basic :basic', ['basic' => number_format((float) $current->basic, 2)]) }} · {{ $categories[$current->tax_category] ?? $current->tax_category }}</p>
                    @include('payroll::partials.breakdown', ['breakdown' => $breakdown])
                @else
                    <x-empty-state :title="__('No salary on this date')" />
                @endif
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('Salary history')" icon="bi-clock-history" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-salary-history>
                    <thead><tr><th class="ps-3">{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Structure') }}</th><th class="text-end">{{ __('Basic') }}</th>@if ($canManage)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($history as $record)
                            <tr data-salary="{{ $record->effective_from->toDateString() }}">
                                <td class="ps-3">{{ $record->effective_from->format('d M Y') }}</td>
                                <td>{{ $record->effective_to?->format('d M Y') ?? __('now') }}</td>
                                <td>{{ $record->structure->name }}</td>
                                <td class="text-end font-monospace">{{ number_format((float) $record->basic, 2) }}</td>
                                @if ($canManage)
                                    <td class="text-end pe-3">@if ($record->effective_from->toDateString() > $today)<x-confirm-delete :action="route('payroll.salaries.destroy', [$person->id, $record->id])" :text="__('This salary has not started yet and will be removed.')" icon-only />@endif</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state :title="__('No salary yet')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
            @if ($canManage)
                <x-card :title="__('Set a salary')" icon="bi-plus-circle"
                        x-data="{ structure: '{{ old('salary_structure_id', $current?->salary_structure_id) }}', sets: @js($employeeSet) }">
                    <form method="POST" action="{{ route('payroll.salaries.store', $person->id) }}" data-salary-form>
                        @csrf
                        <div class="row">
                            <div class="col-md-6"><x-form.select name="salary_structure_id" :label="__('Salary structure')" :options="$structures" :value="$current?->salary_structure_id" :placeholder="__('Choose…')" required x-model="structure" /></div>
                            <div class="col-md-6"><x-form.date name="effective_from" :label="__('Effective from')" :value="$today" required /></div>
                            <div class="col-md-6"><x-form.input name="basic" type="number" step="0.01" min="0" :label="__('Monthly basic salary')" :value="$current?->basic" required /></div>
                            <div class="col-md-6"><x-form.input name="notes" :label="__('Notes')" /></div>
                            <div class="col-md-6"><x-form.select name="tax_category" :label="__('Taxpayer category')" :options="$categories" :value="$current?->tax_category ?? 'general'" required :search="false" /></div>
                            <div class="col-md-6"><x-form.select name="tax_area" :label="__('Tax area')" :options="$areas" :value="$current?->tax_area ?? 'other'" required :search="false" /></div>
                        </div>
                        <template x-for="item in (sets[structure] ?? [])" :key="item.code">
                            <div class="mb-2" data-set-amount>
                                <label class="form-label small" x-text="item.name + ' (' + item.code + ')'"></label>
                                <input type="number" step="0.01" min="0" class="form-control" :name="`amounts[${item.code}]`" :placeholder="item.default">
                            </div>
                        </template>
                        <button type="submit" class="btn btn-primary">{{ __('Save salary') }}</button>
                        <p class="small text-body-secondary mt-2 mb-0">{{ __('The salary before this one ends the day before. A salary that has started is kept as history.') }}</p>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts::app>
