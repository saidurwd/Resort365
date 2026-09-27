<?php

namespace Modules\Core\Contracts;

use Illuminate\Validation\ValidationException;
use Modules\Core\DTOs\SettingDefinition;

/**
 * Typed tenant/property settings (ARCHITECTURE §5.1). Modules register definitions in their
 * providers and read values with get(); a property value overrides the tenant value, which
 * overrides the default.
 */
interface Settings
{
    public function define(SettingDefinition $definition): void;

    public function definition(string $key): SettingDefinition;

    /**
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array;

    /**
     * Definitions by group label, in registration order.
     *
     * @return array<string, list<SettingDefinition>>
     */
    public function grouped(): array;

    /**
     * The effective value for the current tenant (and property), cast to the setting's type.
     */
    public function get(string $key, ?int $propertyId = null): mixed;

    /**
     * The value stored at exactly this level (tenant when $propertyId is null), or null.
     */
    public function stored(string $key, ?int $propertyId = null): mixed;

    /**
     * Validate and store a value; null removes it (falls back to the next level).
     *
     * @throws ValidationException
     */
    public function set(string $key, mixed $value, ?int $propertyId = null): void;
}
