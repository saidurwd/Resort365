<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\BankAccountKind;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;

/**
 * Registers a cash or bank account: a name and the postable asset account of the chart it keeps. Only bank
 * accounts take statements and carry the bank's name and number.
 */
class SaveBankAccount extends Action
{
    /**
     * @param  array{account_id: int, name: string, kind: string, bank_name?: string|null, account_number?: string|null, is_active?: bool}  $data
     *
     * @throws AccountingRuleViolated
     */
    public function handle(?BankAccount $bank, array $data): BankAccount
    {
        $account = Account::query()->find($data['account_id']);

        if (! $account instanceof Account || $account->type !== AccountType::Asset || $account->is_group) {
            throw new AccountingRuleViolated(__('Choose an asset account that takes postings.'));
        }

        if (BankAccount::query()->where('account_id', $account->id)->when($bank instanceof BankAccount, fn ($query) => $query->whereKeyNot($bank?->id))->exists()) {
            throw new AccountingRuleViolated(__(':account is already a cash or bank account.', ['account' => $account->label()]));
        }

        $kind = BankAccountKind::from($data['kind']);
        $bank ??= new BankAccount;
        $bank->fill([
            'account_id' => $account->id, 'name' => trim($data['name']), 'kind' => $kind, 'is_active' => $data['is_active'] ?? true,
            'bank_name' => $kind === BankAccountKind::Bank ? ($data['bank_name'] ?? null) : null, 'account_number' => $kind === BankAccountKind::Bank ? ($data['account_number'] ?? null) : null,
        ])->save();

        return $bank;
    }
}
