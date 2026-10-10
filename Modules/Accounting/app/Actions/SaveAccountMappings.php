<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Accounting\Services\MappingCatalog;

/**
 * Saves the tenant's account mapping (ARCHITECTURE §7.1): a key set to an account is stored, a key left
 * empty goes back to its default. Only known keys and accounts that take postings are accepted.
 */
class SaveAccountMappings extends Action
{
    public function __construct(private readonly MappingCatalog $catalog) {}

    /**
     * @param  array<string, int|null>  $mappings  posting key => account id
     *
     * @throws AccountingRuleViolated
     */
    public function handle(array $mappings): void
    {
        $known = array_column($this->catalog->keys(), 'key');

        foreach ($mappings as $key => $accountId) {
            if (! in_array($key, $known, true)) {
                throw new AccountingRuleViolated(__('Unknown posting key :key.', ['key' => $key]));
            }

            if ($accountId !== null && ! Account::query()->whereKey($accountId)->where('is_group', false)->where('is_active', true)->exists()) {
                throw new AccountingRuleViolated(__('Choose an active account that takes postings for :key.', ['key' => $key]));
            }
        }

        $this->transaction(function () use ($mappings): void {
            foreach ($mappings as $key => $accountId) {
                $existing = AccountMapping::query()->where('mapping_key', $key)->first();

                if ($accountId === null) {
                    $existing?->delete();
                } elseif ($existing instanceof AccountMapping) {
                    $existing->update(['account_id' => $accountId]);
                } else {
                    AccountMapping::query()->create(['mapping_key' => $key, 'account_id' => $accountId]);
                }
            }
        });
    }
}
