@php($title = $department ? $department->name : __('New department'))

<x-layouts::app :title="$title" :breadcrumbs="[__('Departments') => route('property.departments.index'), $title => null]">
    <div class="row">
        <div class="col-xl-6">
            <form method="POST" action="{{ $department ? route('property.departments.update', $department) : route('property.departments.store') }}">
                @csrf
                @if ($department)
                    @method('PUT')
                @endif
                <x-card :title="__('Department')" icon="bi-diagram-3">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="code" :label="__('Code')" :value="$department?->code" required :help="__('E.g. FO, HK.')" /></div>
                        <div class="col-md-8"><x-form.input name="name" :label="__('Name')" :value="$department?->name" required /></div>
                        <div class="col-md-8">
                            <x-form.field name="description" :label="__('Description')">
                                <textarea name="description" id="field-description" rows="2" @class(['form-control', 'is-invalid' => $errors->has('description')])>{{ old('description', $department?->description) }}</textarea>
                            </x-form.field>
                        </div>
                        <div class="col-md-4"><x-form.input name="sort_order" type="number" min="0" :label="__('Sort order')" :value="$department?->sort_order ?? 0" /></div>
                    </div>
                    @include('property::partials.switch', ['name' => 'is_active', 'label' => __('Active'), 'checked' => $department?->is_active ?? true])
                </x-card>
                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('property.departments.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ __('Save department') }}</button>
                </div>
            </form>
        </div>
        @if ($department)
            <div class="col-xl-6"><x-audit-trail :entries="$history" /></div>
        @endif
    </div>
</x-layouts::app>
