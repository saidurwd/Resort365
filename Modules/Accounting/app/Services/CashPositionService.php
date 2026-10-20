<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Contracts\CashPosition;
use Modules\Accounting\DTOs\CashBalance;
use Modules\Accounting\Models\BankAccount;

class CashPositionService implements CashPosition
{
    public function __construct(private readonly BankLedger $ledger) {}

    public function balances(string $date): array
    {
        return BankAccount::query()->where('is_active', true)->orderBy('kind')->orderBy('name')->get()
            ->map(fn (BankAccount $bank): CashBalance => new CashBalance($bank->name, $bank->kind->value, $this->ledger->balance($bank, $date)))->all();
    }
}
