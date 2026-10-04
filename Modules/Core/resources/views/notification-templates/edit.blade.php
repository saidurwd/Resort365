@php($title = __($definition->label ?? $definition->key))

<x-layouts::app :title="$title" :subtitle="$definition->description ? __($definition->description) : null" :breadcrumbs="[__('Email templates') => route('core.notification-templates.index'), $title => null]">
    <div class="row">
        <div class="col-lg-8">
            <form method="POST" action="{{ route('core.notification-templates.update', ['channel' => $definition->channel, 'key' => $definition->key]) }}" data-template-form>
                @csrf
                @method('PUT')
                <x-card :title="__('Wording')" icon="bi-envelope-paper">
                    @if (count($locales) > 1)
                        <x-form.select name="locale" :label="__('Language')" :options="$locales" :value="$locale" :search="false" required />
                    @else
                        <input type="hidden" name="locale" value="{{ $locale }}">
                    @endif
                    <x-form.input name="subject" :label="$definition->channel === 'mail' ? __('Subject') : __('Title')" :value="$subject" :required="$definition->channel === 'mail'" maxlength="255" />
                    <x-form.field name="body" :label="__('Message')" required :help="__('Plain text. Each line becomes a line of the message; in an email the first line is the greeting.')">
                        <textarea name="body" id="field-body" rows="12" required maxlength="10000" @class(['form-control', 'font-monospace', 'is-invalid' => $errors->has('body')])>{{ old('body', $body) }}</textarea>
                    </x-form.field>
                    <input type="hidden" name="is_active" value="1">
                    <x-slot:footer>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('core.notification-templates.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
                            <button type="submit" class="btn btn-primary">{{ __('Save template') }}</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        </div>
        <div class="col-lg-4">
            <x-card :title="__('Placeholders')" icon="bi-braces">
                <p class="small text-body-secondary">{{ __('Type these where the value should appear:') }}</p>
                <ul class="list-unstyled mb-0" data-placeholders>
                    @foreach ($definition->placeholders as $placeholder)
                        <li><code>{{ '{'.$placeholder.'}' }}</code></li>
                    @endforeach
                </ul>
                @if ($template)
                    <p class="small text-body-secondary mt-3 mb-0">{{ __('You changed this template. Reset it on the list to go back to the default wording.') }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
