@php
    $title = $employee ? $employee->full_name : __('New employee');
@endphp

<x-layouts::app :title="$title" :breadcrumbs="[__('HR') => null, __('Employees') => route('hr.employees.index'), $title => null]">
    <form method="POST" action="{{ $employee ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" data-employee-form>
        @csrf
        @if ($employee)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-xl-8">
                <x-card :title="__('Personal details')" icon="bi-person">
                    <div class="row">
                        <div class="col-md-6"><x-form.input name="first_name" :label="__('First name')" :value="$employee?->first_name" required /></div>
                        <div class="col-md-6"><x-form.input name="last_name" :label="__('Last name')" :value="$employee?->last_name" /></div>
                        <div class="col-md-4"><x-form.select name="gender" :label="__('Gender')" :options="$genders" :value="$employee?->gender?->value" :placeholder="__('Choose…')" :search="false" /></div>
                        <div class="col-md-4"><x-form.select name="marital_status" :label="__('Marital status')" :options="$maritalStatuses" :value="$employee?->marital_status?->value" :placeholder="__('Choose…')" :search="false" /></div>
                        <div class="col-md-4"><x-form.date name="date_of_birth" :label="__('Date of birth')" :value="$employee?->date_of_birth?->toDateString()" /></div>
                        <div class="col-md-6"><x-form.select name="nationality_code" :label="__('Nationality')" :options="$countries" :value="$employee?->nationality_code" :placeholder="__('Choose…')" /></div>
                        <div class="col-md-6"><x-form.input name="phone" type="tel" :label="__('Phone')" :value="$employee?->phone" /></div>
                        <div class="col-md-12"><x-form.input name="email" type="email" :label="__('Email')" :value="$employee?->email" /></div>
                        <div class="col-md-6"><x-form.input name="address[line1]" :label="__('Address line 1')" :value="$employee?->address['line1'] ?? null" /></div>
                        <div class="col-md-6"><x-form.input name="address[line2]" :label="__('Address line 2')" :value="$employee?->address['line2'] ?? null" /></div>
                        <div class="col-md-6"><x-form.input name="address[city]" :label="__('City')" :value="$employee?->address['city'] ?? null" /></div>
                        <div class="col-md-6"><x-form.input name="address[postal_code]" :label="__('Postal code')" :value="$employee?->address['postal_code'] ?? null" /></div>
                    </div>
                </x-card>

                <x-card :title="__('Emergency contact')" icon="bi-telephone-plus">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="emergency_contact[name]" :label="__('Name')" :value="$employee?->emergency_contact['name'] ?? null" /></div>
                        <div class="col-md-4"><x-form.input name="emergency_contact[relation]" :label="__('Relation')" :value="$employee?->emergency_contact['relation'] ?? null" /></div>
                        <div class="col-md-4"><x-form.input name="emergency_contact[phone]" type="tel" :label="__('Phone')" :value="$employee?->emergency_contact['phone'] ?? null" /></div>
                    </div>
                </x-card>

                @if ($sensitive)
                    <x-card :title="__('Identity and bank')" icon="bi-shield-lock">
                        <div class="row">
                            <div class="col-md-4"><x-form.input name="national_id" :label="__('National ID')" autocomplete="off" :help="$employee?->national_id ? __('Stored encrypted. Leave empty to keep :masked.', ['masked' => \Modules\HR\Models\Employee::mask($employee->national_id)]) : __('Stored encrypted.')" /></div>
                            <div class="col-md-4"><x-form.input name="passport_no" :label="__('Passport number')" autocomplete="off" :help="$employee?->passport_no ? __('Leave empty to keep :masked.', ['masked' => \Modules\HR\Models\Employee::mask($employee->passport_no)]) : null" /></div>
                            <div class="col-md-4"><x-form.date name="passport_expiry" :label="__('Passport expiry')" :value="$employee?->passport_expiry?->toDateString()" /></div>
                            <div class="col-md-6"><x-form.input name="bank_name" :label="__('Bank')" :value="$employee?->bank_name" /></div>
                            <div class="col-md-6"><x-form.input name="bank_branch" :label="__('Branch')" :value="$employee?->bank_branch" /></div>
                            <div class="col-md-6"><x-form.input name="bank_account_name" :label="__('Account name')" :value="$employee?->bank_account_name" /></div>
                            <div class="col-md-6"><x-form.input name="bank_account_no" :label="__('Account number')" autocomplete="off" :help="$employee?->bank_account_no ? __('Leave empty to keep :masked.', ['masked' => \Modules\HR\Models\Employee::mask($employee->bank_account_no)]) : __('Stored encrypted.')" /></div>
                        </div>
                    </x-card>
                @endif
            </div>

            <div class="col-xl-4">
                <x-card :title="__('Employment')" icon="bi-briefcase">
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$employee?->department_id" :placeholder="__('Choose…')" required />
                    <x-form.select name="designation_id" :label="__('Designation')" :options="$designations" :value="$employee?->designation_id" :placeholder="__('Choose…')" required />
                    <x-form.select name="grade_id" :label="__('Grade')" :options="$grades" :value="$employee?->grade_id" :placeholder="__('None')" />
                    <x-form.select name="manager_id" :label="__('Reports to')" :options="$manager ? [$manager->id => $manager->employee_no.' · '.$manager->full_name] : []" :value="$manager?->id"
                        :placeholder="__('Type a name or number…')" :tom-options="['remote' => route('hr.employees.search')]" />
                    <x-form.select name="employment_type" :label="__('Employment type')" :options="$types" :value="$employee?->employment_type->value ?? 'permanent'" required :search="false" />
                    <x-form.date name="joined_on" :label="__('Joined on')" :value="$employee?->joined_on->toDateString() ?? now()->toDateString()" required />
                    <x-form.date name="probation_ends_on" :label="__('Probation ends')" :value="$employee?->probation_ends_on?->toDateString()" :help="__('Leave empty for no probation.')" />
                    <x-form.field name="notes" :label="__('Notes')">
                        <textarea name="notes" id="field-notes" rows="3" @class(['form-control', 'is-invalid' => $errors->has('notes')])>{{ old('notes', $employee?->notes) }}</textarea>
                    </x-form.field>
                </x-card>
                @if ($employee)
                    <x-audit-trail :entries="app(\Modules\Core\Contracts\AuditTrail::class)->for($employee)" />
                @endif
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ $employee ? route('hr.employees.show', $employee) : route('hr.employees.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save employee') }}</button>
        </div>
    </form>
</x-layouts::app>
