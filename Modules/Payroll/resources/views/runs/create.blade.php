<x-layouts::app :title="__('New payroll run')" :subtitle="$subtitle" :breadcrumbs="[__('Payroll') => null, __('Payroll runs') => route('payroll.runs.index'), __('New') => null]">
    <div class="row"><div class="col-xl-6">
        <x-card :title="__('Payroll run')" icon="bi-cash-stack">
            <form method="POST" action="{{ route('payroll.runs.store') }}" data-run-form>
                @csrf
                <x-form.select name="month" :label="__('Month')" :options="$months" :value="array_key_first($months)" required :search="false" />
                @if ($annual !== [])
                    <div class="mb-3">
                        <div class="form-label">{{ __('Pay with this run') }}</div>
                        @foreach ($annual as $code => $name)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="include_annual[]" value="{{ $code }}" id="annual-{{ $code }}" @checked(in_array($code, (array) old('include_annual', [])))><label class="form-check-label" for="annual-{{ $code }}">{{ $name }} <span class="small text-body-secondary">({{ __('one payment each') }})</span></label></div>
                        @endforeach
                    </div>
                @endif
                <x-form.input name="notes" :label="__('Notes')" />
                <p class="small text-body-secondary">{{ __('Next you calculate the run from the salaries, attendance, overtime, unpaid leave and loans. Nothing is final until it is approved.') }}</p>
                <button type="submit" class="btn btn-primary">{{ __('Open the run') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::app>
