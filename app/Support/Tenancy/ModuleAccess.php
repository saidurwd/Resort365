<?php

namespace App\Support\Tenancy;

use App\Models\TenantModule;

/**
 * Whether the current tenant may use a module (by its lowercase alias, e.g. "frontoffice").
 * Core modules are always on. Without a row, a module is enabled (plans arrive in Phase 7).
 */
class ModuleAccess
{
    /**
     * Modules every tenant always has.
     */
    public const array ALWAYS_ENABLED = ['core', 'iam', 'platform'];

    /**
     * @var array<int, array<string, bool>> tenant id => module => enabled (per request)
     */
    private array $resolved = [];

    public function __construct(private readonly TenantContext $context) {}

    public function enabled(string $module): bool
    {
        $module = strtolower($module);

        if (in_array($module, self::ALWAYS_ENABLED, true)) {
            return true;
        }

        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return true;
        }

        $this->resolved[$tenantId] ??= TenantModule::query()->pluck('enabled', 'module')->map(fn (mixed $enabled): bool => (bool) $enabled)->all();

        return $this->resolved[$tenantId][$module] ?? true;
    }

    /**
     * Forget cached results (after a module is switched on or off).
     */
    public function flush(): void
    {
        $this->resolved = [];
    }
}
