<?php

namespace App\Support\DTOs;

use ReflectionClass;

/**
 * Base class for data transfer objects: immutable, typed data passed between layers
 * and across module boundaries (docs/ARCHITECTURE.md §4.4).
 *
 * Subclasses are `final readonly` and declare their fields as promoted constructor
 * properties, so `from()` can build them from an array with matching keys.
 */
abstract readonly class Data
{
    /**
     * Build the DTO from an array keyed by constructor parameter names
     * (e.g. `$request->validated()`). Unknown keys are an error.
     *
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): static
    {
        return new ReflectionClass(static::class)->newInstanceArgs($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
