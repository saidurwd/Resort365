<?php

namespace Modules\Platform\Services;

/**
 * The modules a plan can include (Step 7.3), with what each needs to work. Core, IAM, Platform and Reports are part of every plan.
 * A plan always includes what its modules need, so a tenant cannot be left with a booking engine and no rates.
 */
final class ModuleCatalog
{
    /**
     * alias => [label, modules it needs].
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const array MODULES = [
        'property' => ['Properties, cottages and rooms', []],
        'guest' => ['Guests and companies', []],
        'rates' => ['Rates and policies', ['property']],
        'reservation' => ['Reservations and availability', ['property', 'rates', 'guest']],
        'billing' => ['Billing, folios and payments', ['reservation']],
        'frontoffice' => ['Front desk and night audit', ['reservation', 'billing']],
        'housekeeping' => ['Housekeeping and maintenance', ['property', 'reservation']],
        'restaurant' => ['Restaurant and POS', ['property', 'billing']],
        'inventory' => ['Inventory', ['property']],
        'procurement' => ['Procurement', ['inventory', 'property']],
        'accounting' => ['Accounting', []],
        'hr' => ['HR, attendance and leave', ['property']],
        'payroll' => ['Payroll', ['hr', 'property']],
        'website' => ['Public website', ['reservation', 'rates', 'property']],
    ];

    /**
     * @return array<string, string> alias => label
     */
    public static function labels(): array
    {
        return array_map(fn (array $module): string => __($module[0]), self::MODULES);
    }

    /**
     * @return list<string>
     */
    public static function aliases(): array
    {
        return array_keys(self::MODULES);
    }

    /**
     * The modules and everything they need, in catalogue order. Unknown names are dropped.
     *
     * @param  list<string>  $modules
     * @return list<string>
     */
    public static function withDependencies(array $modules): array
    {
        $wanted = [];
        $queue = array_values(array_filter($modules, fn (string $module): bool => isset(self::MODULES[$module])));

        while ($queue !== []) {
            $module = array_shift($queue);

            if (isset($wanted[$module])) {
                continue;
            }

            $wanted[$module] = true;
            array_push($queue, ...self::MODULES[$module][1]);
        }

        return array_values(array_filter(self::aliases(), fn (string $alias): bool => isset($wanted[$alias])));
    }

    /**
     * What a module needs, as aliases.
     *
     * @return list<string>
     */
    public static function needs(string $module): array
    {
        return self::MODULES[$module][1] ?? [];
    }
}
