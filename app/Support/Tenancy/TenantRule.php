<?php

namespace App\Support\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Tenant-scoped validation rules (Standard Step Rule R7). Use these instead of
 * Rule::exists() / Rule::unique() for tenant-owned tables.
 *
 *     'room_id' => ['required', TenantRule::exists('rooms')],
 *     'number' => ['required', TenantRule::unique('rooms', 'number')->ignore($room)],
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('tenant_id', self::tenantId());
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)->where('tenant_id', self::tenantId());
    }

    private static function tenantId(): int
    {
        return app(TenantContext::class)->tenantOrFail(self::class)->id;
    }
}
