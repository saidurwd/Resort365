@php($editing = $announcement->exists)
<x-layouts::platform :title="$editing ? $announcement->title : __('New announcement')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Announcements') => route('platform.announcements.index'), $editing ? $announcement->title : __('New') => null]">
    <div class="row"><div class="col-xl-7">
        <x-card :title="__('Announcement')" icon="bi-megaphone">
            <form method="POST" action="{{ $editing ? route('platform.announcements.update', $announcement) : route('platform.announcements.store') }}" data-announcement-form x-data="{ audience: @js(old('audience', $announcement->audience?->value ?? 'all')) }">
                @csrf
                @if ($editing) @method('PUT') @endif
                <x-form.input name="title" :label="__('Title')" :value="old('title', $announcement->title)" required />
                <x-form.input name="body" :label="__('Message')" :value="old('body', $announcement->body)" required />
                <div class="row">
                    <div class="col-md-6"><x-form.select name="level" :label="__('Level')" :options="$levels" :value="old('level', $announcement->level?->value)" required :search="false" /></div>
                    <div class="col-md-6 mb-3"><label class="form-label" for="audience">{{ __('Shown to') }}</label>
                        <select class="form-select" id="audience" name="audience" x-model="audience" required>@foreach ($audiences as $value => $label)<option value="{{ $value }}" @selected(old('audience', $announcement->audience?->value) === $value)>{{ $label }}</option>@endforeach</select></div>
                </div>
                <div class="mb-3" x-show="audience === 'plans'"><label class="form-label" for="plan_ids">{{ __('Plans') }}</label>
                    <select class="form-select" id="plan_ids" name="plan_ids[]" multiple>@foreach ($plans as $id => $name)<option value="{{ $id }}" @selected(in_array($id, old('plan_ids', $announcement->plan_ids ?? [])))>{{ $name }}</option>@endforeach</select></div>
                <div class="mb-3" x-show="audience === 'tenants'"><label class="form-label" for="tenant_ids">{{ __('Tenants') }}</label>
                    <select class="form-select" id="tenant_ids" name="tenant_ids[]" multiple>@foreach ($tenants as $id => $name)<option value="{{ $id }}" @selected(in_array($id, old('tenant_ids', $announcement->tenant_ids ?? [])))>{{ $name }}</option>@endforeach</select></div>
                <div class="row">
                    <div class="col-md-6"><x-form.input name="starts_at" type="datetime-local" :label="__('From')" :value="old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i'))" /></div>
                    <div class="col-md-6"><x-form.input name="ends_at" type="datetime-local" :label="__('Until')" :value="old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i'))" :help="__('Empty: until you delete it.')" /></div>
                </div>
                <button type="submit" class="btn btn-primary" data-save-announcement>{{ __('Save') }}</button>
            </form>
        </x-card>
    </div></div>
</x-layouts::platform>
