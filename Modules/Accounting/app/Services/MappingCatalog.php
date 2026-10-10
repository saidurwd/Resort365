<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\PostingKey;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\Enums\PaymentMethod;

/**
 * Every posting key a tenant can map to an account (Step 4.2): the fixed accounts, one per payment method
 * and one per charge code, each with the default it falls back to.
 */
class MappingCatalog
{
    public function __construct(private readonly LedgerFacts $facts) {}

    /**
     * @return list<array{key: string, label: string, group: string, category: string|null}>
     */
    public function keys(): array
    {
        $rows = [];

        foreach (PostingKey::cases() as $key) {
            $rows[] = ['key' => $key->value, 'label' => $key->label(), 'group' => __('Ledger accounts'), 'category' => null];
        }

        foreach (PaymentMethod::cases() as $method) {
            $rows[] = ['key' => AccountResolver::methodKey($method), 'label' => $method->label(), 'group' => __('Where payments are received'), 'category' => null];
        }

        foreach ($this->facts->chargeCodes() as $code) {
            $rows[] = ['key' => AccountResolver::chargeKey($code['code']), 'label' => $code['code'].' · '.$code['name'], 'group' => __('Revenue by charge code'), 'category' => $code['category']];
        }

        return $rows;
    }
}
