{{--
    A manual journal entry as a draft (ARCHITECTURE §5.14): header and lines with dimensions. Alpine (journalLines)
    shows the totals and balance as figures are typed; the server decides everything when saving or posting.
--}}
<x-layouts::app :title="$entry ? __('Change draft') : __('New journal entry')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Accounting') => null, __('Journal entries') => route('accounting.journals.index'), ($entry ? __('Change draft') : __('New entry')) => null]">
    <form method="POST" action="{{ $entry ? route('accounting.journals.update', $entry) : route('accounting.journals.store') }}" data-journal-form
        x-data="journalLines(@js(['lines' => array_map(fn ($line) => [...$line, 'open' => ! empty($line['property_id']) || ! empty($line['department_id']) || ! empty($line['party_type'])], $lines)]))">
        @csrf
        @if ($entry) @method('PUT') @endif

        <x-card :title="__('Entry')" icon="bi-journal-text">
            <div class="row">
                <div class="col-md-3"><x-form.input name="entry_date" type="date" :label="__('Date')" :value="old('entry_date', $entry?->entry_date->toDateString() ?? now()->toDateString())" required /></div>
                <div class="col-md-6"><x-form.input name="description" :label="__('Description')" :value="old('description', $entry?->description)" required /></div>
                <div class="col-md-3"><x-form.input name="reference" :label="__('Reference')" :value="old('reference', $entry?->reference)" /></div>
            </div>
        </x-card>

        <x-card :title="__('Lines')" icon="bi-list-ul" body-class="p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" data-journal-lines>
                    <thead><tr><th class="ps-3" style="min-width: 18rem">{{ __('Account') }}</th><th class="text-end">{{ __('Debit') }} ({{ $currency }})</th><th class="text-end">{{ __('Credit') }} ({{ $currency }})</th><th>{{ __('Memo') }}</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(line, index) in lines" :key="index">
                            <tr>
                                <td class="ps-3 align-top">
                                    <select class="form-select form-select-sm" :name="`lines[${index}][account_id]`" x-model="line.account_id" :aria-label="'{{ __('Account of line') }} ' + (index + 1)" required>
                                        <option value="">{{ __('Choose an account') }}</option>
                                        @foreach ($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['label'] }}</option>@endforeach
                                    </select>
                                    <div class="row g-1 mt-1" x-show="line.open" x-cloak data-dimensions>
                                        <div class="col-6"><select class="form-select form-select-sm" :name="`lines[${index}][property_id]`" x-model="line.property_id" aria-label="{{ __('Property') }}"><option value="">{{ __('Property') }}</option>@foreach ($properties as $property)<option value="{{ $property['id'] }}">{{ $property['label'] }}</option>@endforeach</select></div>
                                        <div class="col-6"><select class="form-select form-select-sm" :name="`lines[${index}][department_id]`" x-model="line.department_id" aria-label="{{ __('Department') }}"><option value="">{{ __('Department') }}</option>@foreach ($departments as $department)<option value="{{ $department['id'] }}">{{ $department['label'] }}</option>@endforeach</select></div>
                                        <div class="col-6"><select class="form-select form-select-sm" :name="`lines[${index}][party_type]`" x-model="line.party_type" aria-label="{{ __('Party') }}"><option value="">{{ __('Party') }}</option>@foreach ($partyTypes as $type)<option value="{{ $type['value'] }}">{{ $type['label'] }}</option>@endforeach</select></div>
                                        <div class="col-6"><input type="number" min="1" class="form-control form-control-sm" :name="`lines[${index}][party_id]`" x-model="line.party_id" placeholder="{{ __('Party id') }}" aria-label="{{ __('Party id') }}"></div>
                                    </div>
                                </td>
                                <td class="align-top"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end font-monospace" :name="`lines[${index}][debit]`" x-model="line.debit" @input="debited(line)" :aria-label="'{{ __('Debit of line') }} ' + (index + 1)"></td>
                                <td class="align-top"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end font-monospace" :name="`lines[${index}][credit]`" x-model="line.credit" @input="credited(line)" :aria-label="'{{ __('Credit of line') }} ' + (index + 1)"></td>
                                <td class="align-top"><input type="text" maxlength="300" class="form-control form-control-sm" :name="`lines[${index}][description]`" x-model="line.description" aria-label="{{ __('Memo') }}"></td>
                                <td class="align-top text-nowrap pe-3">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="line.open = ! line.open" title="{{ __('Property, department, party') }}" aria-label="{{ __('Dimensions') }}"><i class="bi bi-diagram-3"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="remove(index)" :disabled="lines.length <= 2" aria-label="{{ __('Remove line') }}"><i class="bi bi-x-lg"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td class="ps-3"><button type="button" class="btn btn-sm btn-outline-primary" @click="add()" data-add-line><i class="bi bi-plus-lg"></i> {{ __('Add line') }}</button></td>
                            <td class="text-end font-monospace" x-text="money(debit)" data-total-debit></td>
                            <td class="text-end font-monospace" x-text="money(credit)" data-total-credit></td>
                            <td colspan="2"><span class="badge" :class="balanced ? 'text-bg-success' : 'text-bg-warning'" data-balance x-text="balanced ? '{{ __('Balanced') }}' : '{{ __('Out by') }} ' + money(Math.abs(difference))"></span></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>

        <div class="d-flex gap-2">
            <button type="submit" name="action" value="save" class="btn btn-primary" data-save-draft><i class="bi bi-save"></i> {{ __('Save draft') }}</button>
            @if ($canPost)
                <button type="submit" name="action" value="post" class="btn btn-success" data-post-entry><i class="bi bi-check2-circle"></i> {{ __('Save and post') }}</button>
            @endif
            <a href="{{ $entry ? route('accounting.journals.show', $entry) : route('accounting.journals.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts::app>
