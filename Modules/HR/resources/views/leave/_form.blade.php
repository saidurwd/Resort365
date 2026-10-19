{{-- The leave request form. $manage lets the user pick the employee; $own is the signed-in user's employee. --}}
<form method="POST" action="{{ route('hr.leave.store') }}" data-leave-form>
    @csrf
    @if ($manage)
        <x-form.select name="employee_id" :label="__('Employee')" :options="$chosen ? [$chosen->id => $chosen->employee_no.' · '.$chosen->full_name] : ($own ? [$own->id => $own->employee_no.' · '.$own->full_name] : [])" :value="$chosen?->id ?? $own?->id"
            :placeholder="__('Type a name or number…')" :tom-options="['remote' => route('hr.employees.search')]" required />
    @elseif ($own)
        <p class="mb-3"><span class="text-body-secondary">{{ __('For') }}</span> <strong>{{ $own->full_name }}</strong> <span class="small text-body-secondary">{{ $own->employee_no }}</span></p>
    @else
        <div class="alert alert-warning">{{ __('Your user account is not linked to an employee record, so ask HR to request leave for you.') }}</div>
    @endif
    <x-form.select name="leave_type_id" :label="__('Leave type')" :options="$types" :placeholder="__('Choose…')" required />
    <div class="row">
        <div class="col-md-6"><x-form.date name="from_date" :label="__('From')" required /></div>
        <div class="col-md-6"><x-form.date name="to_date" :label="__('To')" required /></div>
    </div>
    <x-form.select name="half_day" :label="__('Half day')" :options="$halves" :placeholder="__('Whole days')" :search="false" :help="__('Only for a single date.')" />
    <x-form.field name="reason" :label="__('Reason')">
        <textarea name="reason" id="field-reason" rows="2" @class(['form-control', 'is-invalid' => $errors->has('reason')])>{{ old('reason') }}</textarea>
    </x-form.field>
    <p class="small text-body-secondary">{{ __('Days off and holidays are not counted. You can add a medical certificate or other paper on the request page afterwards.') }}</p>
    <button type="submit" class="btn btn-primary" @disabled(! $manage && ! $own)><i class="bi bi-send"></i> {{ __('Ask for leave') }}</button>
</form>
