<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\PostingKey;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Billing\Enums\PaymentMethod;

/**
 * Finds the ledger account for a posting key (ARCHITECTURE §7.1, Step 4.2): the tenant's mapping if it
 * has one, otherwise the chart account that carries the key's default system key.
 */
class AccountResolver
{
    /** Default system key of the standard charge codes; other codes follow their category. */
    private const array CHARGE_DEFAULTS = [
        'ROOM' => 'room_revenue', 'EXBED' => 'extra_bed_revenue', 'FNB' => 'food_revenue', 'TRANSFER' => 'transport_revenue',
        'LAUNDRY' => 'laundry_revenue', 'SPA' => 'spa_revenue', 'MISC' => 'misc_revenue',
    ];

    private const array CATEGORY_DEFAULTS = ['room' => 'room_revenue', 'food_beverage' => 'food_revenue'];

    private const array METHOD_DEFAULTS = [
        'cash' => 'cash_on_hand', 'card' => 'card_clearing', 'bank_transfer' => 'bank', 'mobile_wallet' => 'wallet_clearing',
    ];

    /** @var array<string, int>|null */
    private ?array $mapped = null;

    /** @var array<string, int>|null */
    private ?array $bySystemKey = null;

    public function fixed(PostingKey $key): int
    {
        return $this->resolve($key->value, $key->value, $key->label());
    }

    public function method(PaymentMethod $method): int
    {
        return $this->resolve(self::methodKey($method), self::METHOD_DEFAULTS[$method->value], $method->label());
    }

    public function charge(?string $code, string $category): int
    {
        $default = ($code !== null ? self::CHARGE_DEFAULTS[strtoupper($code)] ?? null : null) ?? self::CATEGORY_DEFAULTS[$category] ?? 'misc_revenue';

        return $this->resolve($code !== null ? self::chargeKey($code) : 'charge:MISC', $default, $code ?? __('Miscellaneous'));
    }

    /** Service charge goes to its own payable account, every other tax to VAT and other taxes payable. */
    public function tax(string $name): int
    {
        return $this->fixed(self::isServiceCharge($name) ? PostingKey::ServiceChargePayable : PostingKey::VatPayable);
    }

    /**
     * A restaurant bill's tender: cash, card, wallet and bank transfer where the money lands, a room charge on the
     * guest ledger, a city ledger bill on the city ledger. Meal plans and complimentary bills have none (null).
     */
    public function restaurantTender(string $method): ?int
    {
        return match ($method) {
            'cash' => $this->method(PaymentMethod::Cash),
            'card' => $this->method(PaymentMethod::Card),
            'wallet' => $this->method(PaymentMethod::MobileWallet),
            'bank_transfer' => $this->method(PaymentMethod::BankTransfer),
            'room_charge' => $this->fixed(PostingKey::GuestLedger),
            'city_ledger' => $this->fixed(PostingKey::CityLedger),
            default => null,
        };
    }

    /** Food or beverage revenue of an outlet: its mapped account, else the chart's food or beverage revenue. */
    public function outletRevenue(int $outletId, string $class): int
    {
        return $this->resolve(self::outletKey($outletId, $class), $class === 'beverage' ? 'beverage_revenue' : 'food_revenue', __('Outlet :id :class revenue', ['id' => $outletId, 'class' => $class]));
    }

    public static function outletKey(int $outletId, string $class): string
    {
        return 'outlet:'.$outletId.':'.$class;
    }

    public static function isServiceCharge(string $name): bool
    {
        return stripos($name, 'service') !== false;
    }

    public static function methodKey(PaymentMethod $method): string
    {
        return 'method:'.$method->value;
    }

    public static function chargeKey(string $code): string
    {
        return 'charge:'.strtoupper($code);
    }

    /**
     * The account a key would use without a mapping, as a system key.
     */
    public static function defaultSystemKey(string $key, ?string $category = null): ?string
    {
        if (PostingKey::tryFrom($key) instanceof PostingKey) {
            return $key;
        }

        if (str_starts_with($key, 'method:')) {
            return self::METHOD_DEFAULTS[substr($key, 7)] ?? null;
        }

        if (str_starts_with($key, 'outlet:')) {
            return str_ends_with($key, ':beverage') ? 'beverage_revenue' : 'food_revenue';
        }

        if (str_starts_with($key, 'charge:')) {
            return self::CHARGE_DEFAULTS[substr($key, 7)] ?? self::CATEGORY_DEFAULTS[$category ?? ''] ?? 'misc_revenue';
        }

        return null;
    }

    /**
     * The account id a key resolves to now, or null when neither a mapping nor a default exists.
     */
    public function current(string $key, ?string $category = null): ?int
    {
        $mapped = $this->mappings()[$key] ?? null;

        if ($mapped !== null) {
            return $mapped;
        }

        $system = self::defaultSystemKey($key, $category);

        return $system !== null ? ($this->systemKeys()[$system] ?? null) : null;
    }

    /**
     * The account a key falls back to when the tenant has not mapped it.
     */
    public function defaultAccountId(string $key, ?string $category = null): ?int
    {
        $system = self::defaultSystemKey($key, $category);

        return $system !== null ? ($this->systemKeys()[$system] ?? null) : null;
    }

    /**
     * @throws AccountingRuleViolated
     */
    private function resolve(string $key, string $defaultSystemKey, string $name): int
    {
        return $this->mappings()[$key] ?? $this->systemKeys()[$defaultSystemKey]
            ?? throw new AccountingRuleViolated(__('No ledger account is set for ":name". Map it under Accounting → Account mapping.', ['name' => $name]));
    }

    /**
     * @return array<string, int>
     */
    private function mappings(): array
    {
        return $this->mapped ??= AccountMapping::query()->pluck('account_id', 'mapping_key')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return array<string, int>
     */
    private function systemKeys(): array
    {
        return $this->bySystemKey ??= Account::query()->whereNotNull('system_key')->pluck('id', 'system_key')->map(fn ($id): int => (int) $id)->all();
    }
}
