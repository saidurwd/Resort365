<x-layouts::app :title="__('Units of measure')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Units') => null]">
    <x-slot:actions>
        @if ($canManage)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unit-modal" data-new-unit><i class="bi bi-plus-lg"></i> {{ __('Add unit') }}</button>@endif
    </x-slot:actions>
    <div x-data="{
        action: @js(route('inventory.units.store')), method: 'POST', title: @js(__('Add unit')), unit: { code: '', name: '', kind: 'count', base_unit_id: '', factor: '', is_active: true },
        blank() { this.action = @js(route('inventory.units.store')); this.method = 'POST'; this.title = @js(__('Add unit')); this.unit = { code: '', name: '', kind: 'count', base_unit_id: '', factor: '', is_active: true }; },
        edit(unit, url) { this.action = url; this.method = 'PUT'; this.title = @js(__('Change unit')); this.unit = { ...unit }; },
    }">
        <x-card body-class="p-0">
            <table class="table table-sm table-hover align-middle mb-0" data-units>
                <thead><tr><th class="ps-3">{{ __('Code') }}</th><th>{{ __('Unit') }}</th><th>{{ __('Kind') }}</th><th>{{ __('Equals') }}</th><th class="text-end">{{ __('Items') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($units as $unit)
                        <tr data-unit="{{ $unit->code }}">
                            <td class="ps-3 font-monospace">{{ $unit->code }}</td><td>{{ $unit->name }} @unless ($unit->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless</td>
                            <td><x-status-badge :status="$unit->kind" /></td>
                            <td>@if ($unit->baseUnit){{ rtrim(rtrim((string) $unit->factor, '0'), '.') }} {{ $unit->baseUnit->code }}@elseif ($unit->kind === \Modules\Inventory\Enums\UnitKind::Pack)<span class="text-body-secondary small">{{ __('set on each item') }}</span>@else<span class="text-body-secondary small">{{ __('base unit') }}</span>@endif</td>
                            <td class="text-end">{{ $unit->items_count }}</td>
                            <td class="text-end pe-3">@if ($canManage)<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#unit-modal"
                                @click="edit(@js(['code' => $unit->code, 'name' => $unit->name, 'kind' => $unit->kind->value, 'base_unit_id' => $unit->base_unit_id, 'factor' => $unit->base_unit_id ? rtrim(rtrim((string) $unit->factor, '0'), '.') : '', 'is_active' => $unit->is_active]), @js(route('inventory.units.update', $unit)))">{{ __('Change') }}</button>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
        @if ($canManage)
            <div class="modal fade" id="unit-modal" tabindex="-1" aria-labelledby="unit-modal-title" aria-hidden="true" @if ($errors->any()) data-show-on-load @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" :action="action" class="modal-content" data-unit-form>
                        @csrf
                        <input type="hidden" name="_method" :value="method">
                        <div class="modal-header"><h1 class="modal-title fs-5" id="unit-modal-title" x-text="title"></h1><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-4 mb-3"><label class="form-label" for="unit-code">{{ __('Code') }}</label><input class="form-control" id="unit-code" name="code" x-model="unit.code" maxlength="12" required></div>
                                <div class="col-8 mb-3"><label class="form-label" for="unit-name">{{ __('Name') }}</label><input class="form-control" id="unit-name" name="name" x-model="unit.name" maxlength="60" required></div>
                            </div>
                            <div class="mb-3"><label class="form-label" for="unit-kind">{{ __('Kind') }}</label><select class="form-select" id="unit-kind" name="kind" x-model="unit.kind">@foreach ($kinds as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="row" x-show="unit.kind !== 'pack'">
                                <div class="col-7 mb-3"><label class="form-label" for="unit-base">{{ __('Defined as a number of') }}</label><select class="form-select" id="unit-base" name="base_unit_id" x-model="unit.base_unit_id"><option value="">{{ __('Nothing: this is a base unit') }}</option>@foreach ($bases as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="col-5 mb-3"><label class="form-label" for="unit-factor">{{ __('How many') }}</label><input type="number" step="0.000001" min="0" class="form-control" id="unit-factor" name="factor" x-model="unit.factor"></div>
                            </div>
                            <input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" id="unit-active" name="is_active" value="1" x-model="unit.is_active"><label class="form-check-label" for="unit-active">{{ __('Active') }}</label></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
