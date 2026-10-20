<x-layouts::app :title="__('Who shares')" :subtitle="$subtitle" :breadcrumbs="[__('Payroll') => null, __('Service charge and tips') => route('payroll.pools.index'), __('Who shares') => null]">
    <p class="text-body-secondary">{{ __('Everyone shares in the pools with one point unless you say otherwise. Points count when a pool is shared by points. Work a pool out again after changing them.') }}</p>
    <form method="POST" action="{{ route('payroll.pools.participants.save') }}" data-participants-form>
        @csrf
        <x-card body-class="p-0">
            <table class="table table-sm align-middle mb-0" data-participants-table>
                <thead><tr><th class="ps-3">{{ __('Employee') }}</th><th>{{ __('Designation') }}</th><th class="text-center">{{ __('Shares') }}</th><th style="width: 9rem">{{ __('Points') }}</th></tr></thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr data-participant="{{ $row['employee']->employeeNo }}">
                            <td class="ps-3">{{ $row['employee']->employeeNo }} · {{ $row['employee']->name }}<input type="hidden" name="participants[{{ $i }}][employee_id]" value="{{ $row['employee']->id }}"></td>
                            <td>{{ $row['employee']->designation }}</td>
                            <td class="text-center"><input type="hidden" name="participants[{{ $i }}][eligible]" value="0"><input type="checkbox" class="form-check-input" name="participants[{{ $i }}][eligible]" value="1" @checked($row['eligible']) @disabled(! $canManage)></td>
                            <td><input type="number" step="0.01" min="0" max="1000" class="form-control form-control-sm" name="participants[{{ $i }}][points]" value="{{ $row['points'] }}" @readonly(! $canManage)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
        @if ($canManage)
            <button type="submit" class="btn btn-primary mt-3">{{ __('Save') }}</button>
        @endif
    </form>
</x-layouts::app>
