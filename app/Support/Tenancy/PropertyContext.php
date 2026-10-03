<?php

namespace App\Support\Tenancy;

/**
 * The properties the signed-in user may access and the one they are working in
 * (ARCHITECTURE §4.2 "Property"). Filled by SetCurrentProperty for web requests; scoped
 * per request. Without a user (console, jobs) it is unrestricted: only the tenant scope applies.
 */
class PropertyContext
{
    /**
     * @var array<int, string>|null id => name; null means unrestricted
     */
    private ?array $accessible = null;

    private ?int $current = null;

    /**
     * Restrict to these properties (id => name) and select the current one.
     *
     * @param  array<int, string>  $accessible
     */
    public function restrictTo(array $accessible, ?int $current): void
    {
        $this->accessible = $accessible;
        $this->current = $current !== null && isset($accessible[$current]) ? $current : (array_key_first($accessible) ?? null);
    }

    public function clear(): void
    {
        $this->accessible = null;
        $this->current = null;
    }

    public function isRestricted(): bool
    {
        return $this->accessible !== null;
    }

    /**
     * @return array<int, string> id => name (empty when unrestricted)
     */
    public function accessible(): array
    {
        return $this->accessible ?? [];
    }

    /**
     * @return list<int>|null null when unrestricted
     */
    public function accessibleIds(): ?array
    {
        return $this->accessible === null ? null : array_keys($this->accessible);
    }

    public function canAccess(int $propertyId): bool
    {
        return $this->accessible === null || isset($this->accessible[$propertyId]);
    }

    public function currentId(): ?int
    {
        return $this->current;
    }

    public function currentName(): ?string
    {
        return $this->current !== null ? ($this->accessible[$this->current] ?? null) : null;
    }
}
