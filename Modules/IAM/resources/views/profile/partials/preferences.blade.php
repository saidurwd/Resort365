<x-card :title="__('Preferences')" icon="bi-sliders">
    <form method="POST" action="{{ route('iam.profile.preferences.update') }}">
        @csrf
        @method('PATCH')
        <div class="row">
            <div class="col-sm-6">
                <x-form.select name="locale" :label="__('Language')" :options="$locales" :value="$user->locale" required :search="false" />
            </div>
            <div class="col-sm-6">
                <x-form.select name="theme" :label="__('Colour mode')" :options="['light' => __('Light'), 'dark' => __('Dark'), 'auto' => __('Auto (follow device)')]" :value="$user->theme ?? 'auto'" required :search="false" />
            </div>
        </div>
        <button type="submit" class="btn btn-primary">{{ __('Save preferences') }}</button>
    </form>
</x-card>
