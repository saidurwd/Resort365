<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;

/**
 * Creates or edits a ledger account (ARCHITECTURE §5.14): a unique code, a name, a type, an optional parent
 * header. A child has its parent's type; only a group can be a parent; a group takes no postings; the
 * type of an account with postings cannot change, nor can it become a group; an account cannot be its own
 * ancestor. System accounts keep their system key.
 */
class SaveAccount extends Action
{
    /**
     * @param  array{code: string, name: string, type: string, parent_id?: int|null, is_group?: bool, is_active?: bool, usali_department?: string|null, description?: string|null}  $data
     *
     * @throws AccountingRuleViolated
     */
    public function handle(?Account $account, array $data): Account
    {
        $type = AccountType::from($data['type']);
        $parent = isset($data['parent_id']) ? Account::query()->find($data['parent_id']) : null;
        $group = (bool) ($data['is_group'] ?? false);

        if ($parent instanceof Account && ! $parent->is_group) {
            throw new AccountingRuleViolated(__(':account is not a group: only a group can hold other accounts.', ['account' => $parent->label()]));
        }

        if ($parent instanceof Account && $parent->type !== $type) {
            throw new AccountingRuleViolated(__('An account has the type of its group (:type).', ['type' => mb_strtolower($parent->type->label())]));
        }

        if ($account instanceof Account) {
            if ($parent instanceof Account && $this->isInside($parent, $account)) {
                throw new AccountingRuleViolated(__('An account cannot be placed inside itself.'));
            }

            if ($account->lines()->exists() && ($account->type !== $type || $group)) {
                throw new AccountingRuleViolated(__('This account has postings: its type cannot change and it cannot become a group.'));
            }

            if ($account->children()->exists() && ! $group) {
                throw new AccountingRuleViolated(__('This account holds other accounts: it stays a group.'));
            }

            if ($account->children()->where('type', '!=', $type->value)->exists()) {
                throw new AccountingRuleViolated(__('Move or change the accounts inside it before changing its type.'));
            }
        }

        return $this->transaction(function () use ($account, $data, $type, $parent, $group): Account {
            $fields = [
                'parent_id' => $parent?->id, 'code' => trim($data['code']), 'name' => trim($data['name']), 'type' => $type, 'is_group' => $group,
                'is_active' => (bool) ($data['is_active'] ?? true), 'usali_department' => $data['usali_department'] ?? null, 'description' => $data['description'] ?? null,
            ];

            if ($account instanceof Account) {
                $account->fill($fields)->save();

                return $account;
            }

            return Account::query()->create([...$fields, 'sort_order' => (int) preg_replace('/\D/', '', $fields['code'])]);
        });
    }

    private function isInside(Account $candidate, Account $account): bool
    {
        for ($node = $candidate; $node instanceof Account; $node = $node->parent) {
            if ($node->id === $account->id) {
                return true;
            }
        }

        return false;
    }
}
