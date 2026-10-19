<x-layouts::app :title="__('Import attendance')" :subtitle="$subtitle" :breadcrumbs="[__('HR') => null, __('Attendance') => route('hr.attendance.index'), __('Import') => null]">
    <div class="row">
        <div class="col-xl-6">
            <x-card :title="__('Biometric CSV')" icon="bi-upload">
                <form method="POST" action="{{ route('hr.attendance.import.store') }}" enctype="multipart/form-data" data-import-form>
                    @csrf
                    <x-form.field name="file" :label="__('File')" required :help="__('A CSV with a header row. Either one line per punch (employee, date-time) or one line per day (employee, date, in, out).')">
                        <input type="file" name="file" id="field-file" accept=".csv,.txt" required @class(['form-control', 'is-invalid' => $errors->has('file')])>
                    </x-form.field>
                    <p class="small text-body-secondary">{{ __('Employees are matched by employee number (e.g. EMP-0001). The whole file is refused if any line is wrong. Importing a file again replaces the days it imported; days entered by hand are kept.') }}</p>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Import') }}</button>
                </form>
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('Imports so far')" icon="bi-clock-history" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-imports>
                    <thead><tr><th class="ps-3">{{ __('File') }}</th><th class="text-end">{{ __('Lines') }}</th><th class="text-end">{{ __('Days') }}</th><th class="text-end">{{ __('Skipped') }}</th><th>{{ __('When') }}</th></tr></thead>
                    <tbody>
                        @forelse ($imports as $import)
                            <tr><td class="ps-3">{{ $import->filename }}</td><td class="text-end">{{ $import->rows_read }}</td><td class="text-end">{{ $import->days_imported }}</td><td class="text-end">{{ $import->days_skipped }}</td><td>{{ $import->created_at?->inPropertyTime()->format('d M Y H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state :title="__('Nothing imported yet')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts::app>
