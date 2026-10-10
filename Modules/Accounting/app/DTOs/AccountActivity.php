<?php

namespace Modules\Accounting\DTOs;

use App\Support\DTOs\Data;
use Modules\Accounting\Enums\AccountType;

/**
 * One account's figures for a report (Step 4.5): the balance before the period, the period's debits and
 * credits, and the period's net (debit less credit) per column, e.g. per property. Amounts are decimal strings.
 */
final readonly class AccountActivity extends Data
{
    /**
     * @param  array<string, string>  $columns  column key => net debit (credit when negative) of the period
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public AccountType $type,
        public ?int $parentId,
        public bool $isGroup,
        public ?string $usali,
        public string $opening,
        public string $debit,
        public string $credit,
        public array $columns = [],
    ) {}

    public function closing(): string
    {
        return bcsub(bcadd($this->opening, $this->debit, 2), $this->credit, 2);
    }

    public function net(): string
    {
        return bcsub($this->debit, $this->credit, 2);
    }
}
