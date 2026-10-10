<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\DeleteAccount;
use Modules\Accounting\Actions\SaveAccount;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\AccountRequest;
use Modules\Accounting\Models\Account;

/**
 * Accounting → Chart of accounts: the tree of ledger accounts, to add, change, deactivate and delete.
 */
class AccountController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Account::class);
        $accounts = Account::query()->withCount(['lines', 'children'])->orderBy('code')->get();
        $byParent = $accounts->groupBy(fn (Account $account): int => $account->parent_id ?? 0);

        return view('accounting::accounts.index', [
            'byParent' => $byParent,
            'groups' => $accounts->where('is_group', true)->mapWithKeys(fn (Account $account): array => [$account->id => $account->label().' ('.mb_strtolower($account->type->label()).')'])->all(),
            'types' => collect(AccountType::cases())->mapWithKeys(fn (AccountType $type): array => [$type->value => $type->label()])->all(),
            'canManage' => auth()->user()?->can('accounting.account.manage') ?? false,
        ]);
    }

    public function store(AccountRequest $request, SaveAccount $save): RedirectResponse
    {
        Gate::authorize('create', Account::class);

        try {
            $account = $save->handle(null, $request->validated());
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.accounts.index')->with('success', __('Account :account added.', ['account' => $account->label()]));
    }

    public function update(AccountRequest $request, Account $account, SaveAccount $save): RedirectResponse
    {
        Gate::authorize('update', $account);

        try {
            $save->handle($account, $request->validated());
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.accounts.index')->with('success', __('Account saved.'));
    }

    public function destroy(Account $account, DeleteAccount $delete): RedirectResponse
    {
        Gate::authorize('delete', $account);

        try {
            $delete->handle($account);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.accounts.index')->with('success', __('Account deleted.'));
    }
}
