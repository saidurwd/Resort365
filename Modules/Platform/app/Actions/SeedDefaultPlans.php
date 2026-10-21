<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\ModuleCatalog;

/**
 * Adds the three starting plans (Step 7.3) when they are missing: Starter (the front desk side), Professional (every module, with
 * limits) and Enterprise (every module, no limits, API access). Prices are placeholders to be set in the console. Plans that exist
 * are left as they are, so it is safe to run again.
 */
class SeedDefaultPlans extends Action
{
    /**
     * code => [name, description, sort, monthly price, yearly price, properties, rooms, users, storage GB, modules, API access].
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: string, 4: string, 5: int|null, 6: int|null, 7: int|null, 8: int|null, 9: list<string>|null, 10: bool}>
     */
    public const array PLANS = [
        'starter' => ['Starter', 'One resort at the front desk: rooms, bookings, billing and housekeeping.', 10, '49.00', '490.00', 1, 20, 5, 2, ['property', 'guest', 'rates', 'reservation', 'billing', 'frontoffice', 'housekeeping'], false],
        'professional' => ['Professional', 'Several resorts with restaurant, stores, purchasing, accounts, HR and payroll.', 20, '149.00', '1490.00', 3, 150, 30, 20, null, false],
        'enterprise' => ['Enterprise', 'Everything, without limits, with API access.', 30, '399.00', '3990.00', null, null, null, null, null, true],
    ];

    /**
     * @return int how many plans were added
     */
    public function handle(): int
    {
        $added = 0;

        foreach (self::PLANS as $code => [$name, $description, $sort, $monthly, $yearly, $properties, $rooms, $users, $storage, $modules, $api]) {
            if (Plan::query()->where('code', $code)->exists()) {
                continue;
            }

            Plan::query()->create([
                'code' => $code, 'name' => $name, 'description' => $description, 'monthly_price' => $monthly, 'yearly_price' => $yearly, 'currency_code' => 'USD', 'max_properties' => $properties, 'max_rooms' => $rooms,
                'max_users' => $users, 'storage_gb' => $storage, 'modules' => $modules ?? ModuleCatalog::aliases(), 'api_access' => $api, 'is_active' => true, 'sort_order' => $sort,
            ]);
            $added++;
        }

        return $added;
    }
}
