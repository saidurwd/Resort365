<x-layouts::app :title="$workflow->name" :subtitle="__('Approval workflow')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Approval workflows') => route('core.approval-workflows.index'), $workflow->name => null]">
    <form method="POST" action="{{ route('core.approval-workflows.update', $workflow) }}" data-workflow-form
        x-data="{ steps: @js($steps), add() { this.steps.push({ label: '', permission: '', min_amount: '0.00' }); }, remove(i) { this.steps.splice(i, 1); } }">
        @csrf
        @method('PUT')
        <x-card :title="__('Steps')" icon="bi-list-ol">
            <p class="text-body-secondary small">{{ $type?->description }} {{ __('A step applies when the amount reaches its minimum. Approvers hold the permission, and decide in the order of the steps.') }}</p>
            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="wf-active" name="is_active" value="1" @checked(old('is_active', $workflow->is_active))><label class="form-check-label" for="wf-active">{{ __('Approval is required') }}</label></div>
            <template x-for="(step, index) in steps" :key="index">
                <div class="row g-2 align-items-end mb-2" data-step>
                    <div class="col-md-3"><label class="form-label small">{{ __('Step') }} <span x-text="index + 1"></span></label><input class="form-control form-control-sm" :name="`steps[${index}][label]`" x-model="step.label" placeholder="{{ __('General Manager') }}" maxlength="100" required></div>
                    <div class="col-md-5"><label class="form-label small">{{ __('Approvers hold') }}</label>
                        <select class="form-select form-select-sm" :name="`steps[${index}][permission]`" x-model="step.permission" required>
                            <option value="">{{ __('Choose a permission') }}</option>
                            @foreach ($permissions as $name => $label)<option value="{{ $name }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="col-md-3"><label class="form-label small">{{ __('From amount') }}</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" :name="`steps[${index}][min_amount]`" x-model="step.min_amount"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="remove(index)" aria-label="{{ __('Remove step') }}"><i class="bi bi-x-lg"></i></button></div>
                </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary" @click="add()" data-add-step><i class="bi bi-plus-lg"></i> {{ __('Add step') }}</button>
        </x-card>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
            <a href="{{ route('core.approval-workflows.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts::app>
