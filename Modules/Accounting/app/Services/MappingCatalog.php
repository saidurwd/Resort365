<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\PostingKey;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Restaurant\Contracts\RestaurantFacts;

/**
 * Every posting key a tenant can map to an account (Step 4.2): the fixed accounts, one per payment method
 * and one per charge code, each with the default it falls back to.
 */
class MappingCatalog
{
    public function __construct(
        private readonly LedgerFacts $facts,
        private readonly RestaurantFacts $restaurant,
    ) {}

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

        foreach ($this->restaurant->outlets() as $outlet) {
            $rows[] = ['key' => AccountResolver::outletKey($outlet['id'], 'food'), 'label' => $outlet['name'].' · '.__('Food'), 'group' => __('Restaurant revenue by outlet'), 'category' => null];
            $rows[] = ['key' => AccountResolver::outletKey($outlet['id'], 'beverage'), 'label' => $outlet['name'].' · '.__('Beverage'), 'group' => __('Restaurant revenue by outlet'), 'category' => null];
        }

        return $rows;
    }
}
