@php
    $texts = [
        'tagline' => [__('Tagline'), 'input', __('A short line under the name, e.g. "Eco cottages by the sea".')],
        'intro_title' => [__('Welcome title'), 'input', null],
        'intro_body' => [__('Welcome text'), 'textarea', __('The introduction on the home page.')],
        'highlights' => [__('Highlights'), 'textarea', __('One per line, e.g. "Private beach".')],
        'dining_intro' => [__('Dining introduction'), 'textarea', null],
        'house_rules' => [__('House rules'), 'textarea', __('One per line; shown on the Policies page.')],
        'seo_title' => [__('Search engine title'), 'input', __('Shown in search results and the browser tab.')],
        'seo_description' => [__('Search engine description'), 'textarea', __('About 160 characters.')],
    ];
    $value = fn (string $field) => old($field, $site?->getAttribute($field) ?? ($defaults[$field] ?? null));
@endphp

<x-layouts::app :title="__('Website')" :subtitle="$property?->name" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Website') => null]">
    @if ($site)
        <x-slot:actions>
            <a href="{{ route('website.home', ['site' => $site->slug]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" data-preview-link>
                <i class="bi bi-box-arrow-up-right"></i> {{ $site->is_published ? __('Open the website') : __('Preview') }}
            </a>
        </x-slot:actions>
    @endif

    <form method="POST" action="{{ route('website.admin.site.update') }}" data-site-form>
        @csrf
        @method('PUT')

        <x-card :title="__('Site')" icon="bi-globe2">
            <div class="row">
                <div class="col-md-5"><x-form.input name="name" :label="__('Name on the website')" :value="$value('name')" required /></div>
                <div class="col-md-4"><x-form.input name="slug" :label="__('Web address')" :value="$value('slug')" required prepend="/resort/" :help="__('Small letters, numbers and dashes.')" /></div>
                <div class="col-md-3"><x-form.select name="theme" :label="__('Colours')" :options="$themes" :value="old('theme', $site?->theme->value ?? 'forest')" :search="false" required /></div>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="is_published" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="is_published" id="field-is_published" value="1" @checked(old('is_published', $site?->is_published ?? false))>
                <label class="form-check-label" for="field-is_published">{{ __('Published: anyone can open the website') }}</label>
            </div>
        </x-card>

        <x-card :title="__('Contact')" icon="bi-telephone">
            <div class="row">
                <div class="col-md-4"><x-form.input name="phone" :label="__('Phone')" :value="$value('phone')" /></div>
                <div class="col-md-4"><x-form.input name="whatsapp" :label="__('WhatsApp')" :value="$value('whatsapp')" /></div>
                <div class="col-md-4"><x-form.input name="email" type="email" :label="__('Email')" :value="$value('email')" /></div>
                <div class="col-md-12"><x-form.input name="address" :label="__('Address')" :value="$value('address')" /></div>
                <div class="col-md-4"><x-form.input name="map_url" type="url" :label="__('Map link')" :value="$value('map_url')" :help="__('A Google Maps share link.')" /></div>
                <div class="col-md-4"><x-form.input name="facebook_url" type="url" :label="__('Facebook page')" :value="$value('facebook_url')" /></div>
                <div class="col-md-4"><x-form.input name="instagram_url" type="url" :label="__('Instagram')" :value="$value('instagram_url')" /></div>
            </div>
        </x-card>

        <x-card :title="__('Texts')" icon="bi-translate">
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach ($locales as $code => $language)
                    <li class="nav-item" role="presentation">
                        <button @class(['nav-link', 'active' => $loop->first]) type="button" data-bs-toggle="tab" data-bs-target="#texts-{{ $code }}" role="tab" lang="{{ $code }}">{{ $language }}</button>
                    </li>
                @endforeach
            </ul>
            <div class="tab-content">
                @foreach ($locales as $code => $language)
                    <div @class(['tab-pane', 'show active' => $loop->first]) id="texts-{{ $code }}" role="tabpanel" data-texts="{{ $code }}">
                        @if (! $loop->first)
                            <p class="small text-body-secondary">{{ __('Leave a text empty to show the English one.') }}</p>
                        @endif
                        @foreach ($texts as $field => [$label, $control, $help])
                            @php($name = $field.'['.$code.']')
                            @php($current = old($field.'.'.$code, $site?->getAttribute($field)[$code] ?? null))
                            @if ($control === 'input')
                                <x-form.input :name="$name" :label="$label" :value="$current" :help="$help" lang="{{ $code }}" />
                            @else
                                <x-form.field :name="$name" :label="$label" :help="$help">
                                    <textarea name="{{ $name }}" id="{{ \App\Support\Ui\FormField::id($name) }}" rows="3" lang="{{ $code }}" @class(['form-control', 'is-invalid' => $errors->has($field.'.'.$code)])>{{ $current }}</textarea>
                                </x-form.field>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
            <x-slot:footer>
                <div class="d-flex justify-content-end">
                    @can(($site ? 'update' : 'create'), $site ?? \Modules\Website\Models\WebsiteSite::class)
                        <button type="submit" class="btn btn-primary">{{ __('Save website') }}</button>
                    @endcan
                </div>
            </x-slot:footer>
        </x-card>
    </form>

    @if ($site)
        <x-photo-gallery :subject="$site" :title="__('Photos (the first three open the home page)')" />
        <x-audit-trail :entries="$history" />
    @else
        <x-empty-state :title="__('No website yet')" :message="__('Save the settings above to create the website; then add photos.')" icon="bi-globe2" />
    @endif
</x-layouts::app>
