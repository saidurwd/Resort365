<x-layouts::app :title="__('Settings')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Settings') => null]">
    @if ($properties !== [])
        <x-slot:actions>
            <form method="GET" action="{{ route('core.settings.index') }}" class="d-flex align-items-center gap-2" data-settings-scope>
                <label for="settings-scope" class="small text-body-secondary text-nowrap">{{ __('Settings for') }}</label>
                <select id="settings-scope" name="property" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Company-wide') }}</option>
                    @foreach ($properties as $id => $name)
                        <option value="{{ $id }}" @selected($propertyId === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    @endif

    <ul class="nav nav-tabs mb-3">
        @foreach ($groups as $group)
            <li class="nav-item">
                <a @class(['nav-link', 'active' => $group === $active]) href="{{ route('core.settings.index', array_filter(['group' => $group, 'property' => $propertyId])) }}">{{ __($group) }}</a>
            </li>
        @endforeach
    </ul>

    <form method="POST" action="{{ route('core.settings.update', array_filter(['group' => $active, 'property' => $propertyId])) }}">
        @csrf
        @method('PUT')

        <x-card>
            @if ($propertyId !== null)
                <p class="small text-body-secondary" data-property-scope>{{ __('Values for :property only. Leave a field empty to use the company-wide value.', ['property' => $properties[$propertyId]]) }}</p>
            @elseif (collect($definitions)->contains(fn ($d) => $d->scope === \Modules\Core\Enums\SettingScope::Property))
                <p class="small text-body-secondary">{{ __('These are the company-wide values; each property can override the per-property ones (choose it above).') }}</p>
            @endif

            <div class="row">
                @foreach ($definitions as $definition)
                    @php
                        $field = str_replace('.', '__', $definition->key);
                        $value = $values[$definition->key];
                        $type = $definition->type;
                        $inherit = array_key_exists($definition->key, $inherited) ? __('Company-wide: :value', ['value' => is_bool($inherited[$definition->key]) ? ($inherited[$definition->key] ? __('Yes') : __('No')) : $inherited[$definition->key]]) : null;
                        $help = collect([$definition->help ? __($definition->help) : null, $inherit])->filter()->implode(' ');
                    @endphp
                    <div class="col-md-6">
                        @switch($type)
                            @case(\Modules\Core\Enums\SettingType::Boolean)
                                <div class="mb-3">
                                    <input type="hidden" name="{{ $field }}" value="0">
                                    <div class="form-check form-switch mt-4">
                                        <input class="form-check-input" type="checkbox" role="switch" name="{{ $field }}" id="field-{{ $field }}" value="1" @checked(old($field, $value))>
                                        <label class="form-check-label" for="field-{{ $field }}">{{ __($definition->label) }}</label>
                                    </div>
                                    @if ($help)<div class="form-text">{{ $help }}</div>@endif
                                </div>
                                @break
                            @case(\Modules\Core\Enums\SettingType::Select)
                                <x-form.select :name="$field" :label="__($definition->label)" :options="$definition->options" :value="$value" :help="$help ?: null" :search="false" />
                                @break
                            @case(\Modules\Core\Enums\SettingType::Currency)
                            @case(\Modules\Core\Enums\SettingType::Country)
                            @case(\Modules\Core\Enums\SettingType::Timezone)
                                <x-form.select :name="$field" :label="__($definition->label)" :options="($lookups[$type->value])()" :value="$value" :help="$help ?: null" />
                                @break
                            @case(\Modules\Core\Enums\SettingType::Time)
                                <x-form.input :name="$field" type="time" :label="__($definition->label)" :value="$value" :help="$help ?: null" />
                                @break
                            @case(\Modules\Core\Enums\SettingType::Integer)
                            @case(\Modules\Core\Enums\SettingType::Decimal)
                                <x-form.input :name="$field" type="number" :step="$type === \Modules\Core\Enums\SettingType::Decimal ? '0.0001' : '1'" :label="__($definition->label)" :value="$value" :help="$help ?: null" />
                                @break
                            @default
                                <x-form.input :name="$field" :label="__($definition->label)" :value="$value" :help="$help ?: null" />
                        @endswitch
                    </div>
                @endforeach
            </div>

            @can('core.setting.update')
                <x-slot:footer>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">{{ __('Save settings') }}</button>
                    </div>
                </x-slot:footer>
            @endcan
        </x-card>
    </form>
</x-layouts::app>
