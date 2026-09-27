<?php

namespace Modules\Core\Services;

use App\Support\Tenancy\TenantCache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Models\Setting;

/**
 * Settings engine. Definitions live in memory (registered at boot); stored values are cached
 * per tenant (TenantCache) and the cache is dropped whenever a value is written.
 */
class SettingsService implements Settings
{
    private const string CACHE_KEY = 'core.settings';

    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions = [];

    public function define(SettingDefinition $definition): void
    {
        if (isset($this->definitions[$definition->key])) {
            throw new InvalidArgumentException("Setting [{$definition->key}] is defined twice.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function definition(string $key): SettingDefinition
    {
        return $this->definitions[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->definitions as $definition) {
            $grouped[$definition->group][] = $definition;
        }

        return $grouped;
    }

    public function get(string $key, ?int $propertyId = null): mixed
    {
        $definition = $this->definition($key);
        $stored = $this->storedValues();

        if ($propertyId !== null && $definition->scope === SettingScope::Property && array_key_exists($propertyId.':'.$key, $stored)) {
            return $definition->type->cast($stored[$propertyId.':'.$key]);
        }

        if (array_key_exists('0:'.$key, $stored)) {
            return $definition->type->cast($stored['0:'.$key]);
        }

        return $definition->type->cast($definition->default);
    }

    public function stored(string $key, ?int $propertyId = null): mixed
    {
        $definition = $this->definition($key);
        $value = $this->storedValues()[($propertyId ?? 0).':'.$key] ?? null;

        return $definition->type->cast($value);
    }

    public function set(string $key, mixed $value, ?int $propertyId = null): void
    {
        $definition = $this->definition($key);

        if ($propertyId !== null && $definition->scope !== SettingScope::Property) {
            throw new InvalidArgumentException("Setting [{$key}] cannot be set per property.");
        }

        Validator::make(['value' => $value], ['value' => $definition->validationRules()], [], ['value' => __($definition->label)])->validate();

        $existing = Setting::query()->where('key', $key)->where('property_id', $propertyId)->first();

        if ($value === null || $value === '') {
            $existing?->delete();
        } else {
            $setting = $existing ?? new Setting(['key' => $key, 'property_id' => $propertyId]);
            $setting->value = $definition->type->cast($value);
            $setting->save();
        }

        app(TenantCache::class)->forget(self::CACHE_KEY);
    }

    /**
     * Every stored value of the current tenant, keyed "{propertyId or 0}:{key}".
     *
     * @return array<string, mixed>
     */
    private function storedValues(): array
    {
        /** @var array<string, mixed> */
        return app(TenantCache::class)->remember(self::CACHE_KEY, now()->addDay(), fn (): array => Setting::query()
            ->get(['property_id', 'key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [($setting->property_id ?? 0).':'.$setting->key => $setting->value])
            ->all());
    }
}
