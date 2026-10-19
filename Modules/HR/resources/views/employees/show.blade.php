@php
    $mask = fn (?string $value): string => $value === null || $value === '' ? '—' : ($sensitive ? $value : \Modules\HR\Models\Employee::mask($value));
    $address = collect($employee->address ?? [])->filter()->implode(', ');
    $emergency = collect($employee->emergency_contact ?? [])->filter()->implode(' · ');
    $today = now()->toDateString();
    $lifecycleRoute = fn (string $event): string => route('hr.employees.lifecycle', [$employee, $event]);
@endphp

<x-layouts::app :title="$employee->full_name" :subtitle="$employee->employee_no" :breadcrumbs="[__('HR') => null, __('Employees') => route('hr.employees.index'), $employee->full_name => null]">
    <x-slot:actions>
        @if ($canManage && ! $employee->isExited())
            <a href="{{ route('hr.employees.edit', $employee) }}" class="btn btn-sm btn-outline-primary" data-edit-employee><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        @endif
        @if ($canLifecycle && ! $employee->isExited())
            <div class="btn-group" data-lifecycle-menu>
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-diagram-3"></i> {{ __('Lifecycle') }}</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @if ($isProbation)<li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lc-confirm">{{ __('Confirm after probation') }}</button></li>@endif
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lc-promote">{{ __('Promote') }}</button></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lc-increment">{{ __('Record an increment') }}</button></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lc-transfer">{{ __('Transfer') }}</button></li>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lc-disciplinary">{{ __('Disciplinary record') }}</button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#lc-exit">{{ __('Record exit') }}</button></li>
                </ul>
            </div>
        @endif
    </x-slot:actions>

    @if ($employee->isExited())
        <div class="alert alert-secondary d-flex gap-2" role="alert" data-exited-banner>
            <i class="bi bi-box-arrow-right fs-5"></i>
            <div><span class="fw-semibold">{{ __('Left on :date', ['date' => $employee->exited_on?->format('d M Y')]) }}</span> · {{ $employee->exit_reason?->label() }}</div>
        </div>
    @endif

    <ul class="nav nav-tabs mb-3" role="tablist" data-hash-tabs>
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-profile" data-tab-hash="profile"><i class="bi bi-person"></i> {{ __('Profile') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-employment" data-tab-hash="employment"><i class="bi bi-briefcase"></i> {{ __('Employment') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-documents" data-tab-hash="documents"><i class="bi bi-folder2-open"></i> {{ __('Documents') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-history" data-tab-hash="history"><i class="bi bi-clock-history"></i> {{ __('History') }}</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
            <div class="row">
                <div class="col-xl-8">
                    <x-card :title="__('Personal details')" icon="bi-person">
                        <dl class="row mb-0" data-profile>
                            <dt class="col-sm-4">{{ __('Gender') }}</dt><dd class="col-sm-8">{{ $employee->gender?->label() ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Marital status') }}</dt><dd class="col-sm-8">{{ $employee->marital_status?->label() ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Date of birth') }}</dt><dd class="col-sm-8">{{ $employee->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Nationality') }}</dt><dd class="col-sm-8">{{ $employee->nationality_code ? ($countries[$employee->nationality_code] ?? $employee->nationality_code) : '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Phone') }}</dt><dd class="col-sm-8">{{ $employee->phone ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Email') }}</dt><dd class="col-sm-8">{{ $employee->email ?? '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Address') }}</dt><dd class="col-sm-8">{{ $address ?: '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Emergency contact') }}</dt><dd class="col-sm-8">{{ $emergency ?: '—' }}</dd>
                        </dl>
                    </x-card>
                    <x-card :title="__('Identity and bank')" icon="bi-shield-lock">
                        <dl class="row mb-0" data-sensitive>
                            <dt class="col-sm-4">{{ __('National ID') }}</dt><dd class="col-sm-8 font-monospace" data-national-id>{{ $mask($employee->national_id) }}</dd>
                            <dt class="col-sm-4">{{ __('Passport') }}</dt><dd class="col-sm-8 font-monospace">{{ $mask($employee->passport_no) }}@if ($employee->passport_expiry)<span class="text-body-secondary font-sans-serif"> · {{ __('expires :date', ['date' => $employee->passport_expiry->format('d M Y')]) }}</span>@endif</dd>
                            <dt class="col-sm-4">{{ __('Bank') }}</dt><dd class="col-sm-8">{{ collect([$employee->bank_name, $employee->bank_branch])->filter()->implode(' · ') ?: '—' }}</dd>
                            <dt class="col-sm-4">{{ __('Account') }}</dt><dd class="col-sm-8 font-monospace" data-bank-account>{{ $employee->bank_account_name ? $employee->bank_account_name.' · ' : '' }}{{ $mask($employee->bank_account_no) }}</dd>
                        </dl>
                        @unless ($sensitive)<p class="small text-body-secondary mt-2 mb-0">{{ __('Numbers are masked: you need permission to see them in full.') }}</p>@endunless
                    </x-card>
                </div>
                <div class="col-xl-4">
                    <x-photo-gallery :subject="$employee" />
                    <x-card :title="__('Sign-in account')" icon="bi-person-lock">
                        @if ($user)
                            <p class="mb-2" data-linked-user>{{ $user->name }}<br><span class="small text-body-secondary">{{ $user->email }}</span></p>
                        @else
                            <p class="text-body-secondary mb-2">{{ __('Not linked to a user account.') }}</p>
                        @endif
                        @if ($canManage && ! $employee->isExited())
                            <form method="POST" action="{{ route('hr.employees.user', $employee) }}" data-link-user>
                                @csrf
                                @method('PUT')
                                <x-form.select name="user_id" :label="__('User')" :options="$userOptions" :value="$employee->user_id" :placeholder="__('None')" />
                                <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save link') }}</button>
                            </form>
                        @endif
                    </x-card>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-employment" role="tabpanel">
            <div class="row">
                <div class="col-xl-6">
                    <x-card :title="__('Employment')" icon="bi-briefcase">
                        <dl class="row mb-0" data-employment>
                            <dt class="col-sm-5">{{ __('Status') }}</dt><dd class="col-sm-7"><x-status-badge :status="$employee->status" /></dd>
                            <dt class="col-sm-5">{{ __('Property') }}</dt><dd class="col-sm-7">{{ $properties[$employee->property_id] ?? '—' }}</dd>
                            <dt class="col-sm-5">{{ __('Department') }}</dt><dd class="col-sm-7">{{ $departments[$employee->department_id] ?? '—' }}</dd>
                            <dt class="col-sm-5">{{ __('Designation') }}</dt><dd class="col-sm-7">{{ $employee->designation->name }}</dd>
                            <dt class="col-sm-5">{{ __('Grade') }}</dt><dd class="col-sm-7">{{ $employee->grade->name ?? '—' }}</dd>
                            <dt class="col-sm-5">{{ __('Employment type') }}</dt><dd class="col-sm-7">{{ $employee->employment_type->label() }}</dd>
                            <dt class="col-sm-5">{{ __('Joined on') }}</dt><dd class="col-sm-7">{{ $employee->joined_on->format('d M Y') }}</dd>
                            <dt class="col-sm-5">{{ __('Probation ends') }}</dt><dd class="col-sm-7">{{ $employee->probation_ends_on?->format('d M Y') ?? '—' }}</dd>
                            <dt class="col-sm-5">{{ __('Confirmed on') }}</dt><dd class="col-sm-7">{{ $employee->confirmed_on?->format('d M Y') ?? '—' }}</dd>
                            <dt class="col-sm-5">{{ __('Reports to') }}</dt>
                            <dd class="col-sm-7" data-manager>@if ($employee->manager)<a href="{{ route('hr.employees.show', $employee->manager) }}">{{ $employee->manager->full_name }}</a>@else — @endif</dd>
                            @if ($employee->notes)<dt class="col-sm-5">{{ __('Notes') }}</dt><dd class="col-sm-7 mb-0">{!! nl2br(e($employee->notes)) !!}</dd>@endif
                        </dl>
                    </x-card>
                </div>
                <div class="col-xl-6">
                    <x-card :title="__('Direct reports')" icon="bi-diagram-2" body-class="p-0">
                        <ul class="list-group list-group-flush" data-reports>
                            @forelse ($employee->reports->where('status', '!=', \Modules\HR\Enums\EmployeeStatus::Exited) as $report)
                                <li class="list-group-item"><a href="{{ route('hr.employees.show', $report) }}">{{ $report->full_name }}</a> <span class="small text-body-secondary">{{ $report->designation->name }}</span></li>
                            @empty
                                <li class="list-group-item text-body-secondary">{{ __('Nobody reports to :name.', ['name' => $employee->first_name]) }}</li>
                            @endforelse
                        </ul>
                    </x-card>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-documents" role="tabpanel">
            <x-attachments :subject="$employee" :title="__('Contract and certificates')" />
        </div>

        <div class="tab-pane fade" id="tab-history" role="tabpanel">
            <div class="row">
                <div class="col-xl-7">
                    <x-card :title="__('Career')" icon="bi-clock-history" body-class="p-0">
                        <ul class="list-group list-group-flush" data-career>
                            @foreach ($employee->histories as $entry)
                                <li class="list-group-item" data-history="{{ $entry->event->value }}">
                                    <div class="d-flex justify-content-between">
                                        <span><x-status-badge :status="$entry->event" /></span>
                                        <span class="small text-body-secondary">{{ $entry->effective_on->format('d M Y') }}</span>
                                    </div>
                                    @foreach (($entry->details ?? []) as $key => $value)
                                        <div class="small">
                                            {{ \Illuminate\Support\Str::headline((string) $key) }}:
                                            @if (is_array($value))
                                                <del class="text-body-secondary">{{ $value[0] ?? '—' }}</del> → {{ $value[1] ?? '—' }}
                                            @else
                                                {{ \Modules\HR\Enums\DisciplinaryAction::tryFrom((string) $value)?->label() ?? \Modules\HR\Enums\ExitReason::tryFrom((string) $value)?->label() ?? $value }}
                                            @endif
                                        </div>
                                    @endforeach
                                    @if ($entry->notes)<div class="small mt-1">{!! nl2br(e($entry->notes)) !!}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                </div>
                <div class="col-xl-5"><x-audit-trail :entries="$trail" /></div>
            </div>
        </div>
    </div>

    @if ($canLifecycle && ! $employee->isExited())
        @if ($isProbation)
            <x-modal id="lc-confirm" :title="__('Confirm after probation')">
                <form method="POST" action="{{ $lifecycleRoute('confirm') }}" id="form-lc-confirm">
                    @csrf
                    <x-form.date name="effective_on" :label="__('Confirmed on')" :value="$today" required />
                    <x-form.input name="notes" :label="__('Notes')" />
                </form>
                <x-slot:footer><button type="submit" form="form-lc-confirm" class="btn btn-primary">{{ __('Confirm employee') }}</button></x-slot:footer>
            </x-modal>
        @endif
        <x-modal id="lc-promote" :title="__('Promote')">
            <form method="POST" action="{{ $lifecycleRoute('promote') }}" id="form-lc-promote">
                @csrf
                <x-form.select name="designation_id" :label="__('New designation')" :options="$designations" :value="$employee->designation_id" required />
                <x-form.select name="grade_id" :label="__('New grade')" :options="$grades" :value="$employee->grade_id" :placeholder="__('None')" />
                <x-form.date name="effective_on" :label="__('Effective on')" :value="$today" required />
                <x-form.input name="notes" :label="__('Notes')" />
            </form>
            <x-slot:footer><button type="submit" form="form-lc-promote" class="btn btn-primary">{{ __('Promote') }}</button></x-slot:footer>
        </x-modal>
        <x-modal id="lc-increment" :title="__('Record an increment')">
            <form method="POST" action="{{ $lifecycleRoute('increment') }}" id="form-lc-increment">
                @csrf
                <div class="row">
                    <div class="col-6"><x-form.input name="previous_amount" type="number" step="0.01" min="0" :label="__('Amount before')" required /></div>
                    <div class="col-6"><x-form.input name="new_amount" type="number" step="0.01" min="0" :label="__('Amount after')" required /></div>
                </div>
                <x-form.date name="effective_on" :label="__('Effective on')" :value="$today" required />
                <x-form.input name="notes" :label="__('Notes')" />
                <p class="small text-body-secondary mb-0">{{ __('This is a record in the history. Salaries are set up with payroll.') }}</p>
            </form>
            <x-slot:footer><button type="submit" form="form-lc-increment" class="btn btn-primary">{{ __('Record') }}</button></x-slot:footer>
        </x-modal>
        <x-modal id="lc-transfer" :title="__('Transfer')">
            <form method="POST" action="{{ $lifecycleRoute('transfer') }}" id="form-lc-transfer">
                @csrf
                <x-form.select name="property_id" :label="__('Property')" :options="$properties" :value="$employee->property_id" required />
                <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$employee->department_id" :placeholder="__('Keep :name', ['name' => $departments[$employee->department_id] ?? '—'])" />
                <x-form.date name="effective_on" :label="__('Effective on')" :value="$today" required />
                <x-form.input name="notes" :label="__('Notes')" />
            </form>
            <x-slot:footer><button type="submit" form="form-lc-transfer" class="btn btn-primary">{{ __('Transfer') }}</button></x-slot:footer>
        </x-modal>
        <x-modal id="lc-disciplinary" :title="__('Disciplinary record')">
            <form method="POST" action="{{ $lifecycleRoute('disciplinary') }}" id="form-lc-disciplinary">
                @csrf
                <x-form.select name="action" :label="__('Action')" :options="$disciplinary" required :search="false" />
                <x-form.date name="effective_on" :label="__('Date')" :value="$today" required />
                <x-form.field name="notes" :label="__('What happened')" required>
                    <textarea name="notes" id="field-notes" rows="3" required @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes') }}</textarea>
                </x-form.field>
            </form>
            <x-slot:footer><button type="submit" form="form-lc-disciplinary" class="btn btn-primary">{{ __('Record') }}</button></x-slot:footer>
        </x-modal>
        <x-modal id="lc-exit" :title="__('Record exit')">
            <form method="POST" action="{{ $lifecycleRoute('exit') }}" id="form-lc-exit">
                @csrf
                <x-form.select name="reason" :label="__('Reason')" :options="$exitReasons" required :search="false" />
                <x-form.date name="effective_on" :label="__('Last day')" :value="$today" required />
                <x-form.input name="notes" :label="__('Notes')" />
                <p class="small text-body-secondary mb-0">{{ __('People who report to :name will report to their manager.', ['name' => $employee->first_name]) }}</p>
            </form>
            <x-slot:footer><button type="submit" form="form-lc-exit" class="btn btn-danger">{{ __('Record exit') }}</button></x-slot:footer>
        </x-modal>
    @endif
</x-layouts::app>
