<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\ChartOfAccountsTemplate;

/**
 * Gives the tenant the USALI-aligned starting chart of accounts (ARCHITECTURE §5.14). Idempotent: accounts
 * the tenant already has (by code) are left as they are, missing ones are added, so it can be run again
 * after the template grows.
 */
class SeedChartOfAccounts extends Action
{
    public function __construct(
        private readonly ChartOfAccountsTemplate $template,
    ) {}

    /**
     * @return int how many accounts were added
     */
    public function handle(): int
    {
        return $this->transaction(function (): int {
            $existing = Account::query()->pluck('id', 'code')->all();
            $taken = Account::query()->whereNotNull('system_key')->pluck('system_key')->all();
            $added = 0;

            foreach ($this->template->accounts() as $code => [$name, $type, $parent, $group, $systemKey, $usali]) {
                if (isset($existing[$code])) {
                    continue;
                }

                $account = Account::query()->create([
                    'parent_id' => $parent !== null ? ($existing[$parent] ?? null) : null, 'code' => (string) $code, 'name' => $name, 'type' => $type, 'is_group' => $group,
                    'is_active' => true, 'system_key' => $systemKey !== null && ! in_array($systemKey, $taken, true) ? $systemKey : null, 'usali_department' => $usali, 'sort_order' => (int) $code,
                ]);
                $existing[$code] = $account->id;
                $added++;
            }

            return $added;
        });
    }
}
