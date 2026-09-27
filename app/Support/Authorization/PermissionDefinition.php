<?php

namespace App\Support\Authorization;

use App\Support\DTOs\Data;
use InvalidArgumentException;

/**
 * A permission a module registers: `module.resource.action` (ARCHITECTURE §3.3).
 */
final readonly class PermissionDefinition extends Data
{
    /**
     * @param  list<DefaultRole>  $defaultRoles  default roles that get it (Tenant Owner always does; Auditor gets every `*.view`)
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $defaultRoles = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9]*\.[a-z][a-z0-9-]*\.[a-z][a-z0-9-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Permission [{$name}] must be named module.resource.action.");
        }
    }

    public function module(): string
    {
        return explode('.', $this->name, 2)[0];
    }

    public function isView(): bool
    {
        return str_ends_with($this->name, '.view');
    }
}
