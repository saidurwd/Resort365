<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\SaveBankAccount;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\BankAccountKind;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\BankAccountRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Services\BankLedger;

/**
 * Accounting → Bank accounts: the tenant's cash and bank accounts, each tied to a ledger account.
 */
class BankAccountController extends Controller
{
    public function index(BankLedger $ledger): View
    {
        Gate::authorize('viewAny', BankAccount::class);
        $banks = BankAccount::query()->with('account')->orderBy('name')->get();
        $today = now()->toDateString();

        return view('accounting::banking.accounts', [
            'banks' => $banks, 'balances' => $banks->mapWithKeys(fn (BankAccount $bank): array => [$bank->id => $ledger->balance($bank, $today)])->all(),
            'accounts' => Account::query()->where('type', AccountType::Asset->value)->where('is_group', false)->where('is_active', true)->orderBy('code')->get()
                ->mapWithKeys(fn (Account $account): array => [$account->id => $account->label()])->all(),
            'kinds' => collect(BankAccountKind::cases())->mapWithKeys(fn (BankAccountKind $kind): array => [$kind->value => $kind->label()])->all(),
            'canManage' => auth()->user()?->can('accounting.bank.manage') ?? false,
        ]);
    }

    public function store(BankAccountRequest $request, SaveBankAccount $save): RedirectResponse
    {
        return $this->save($request, null, $save);
    }

    public function update(BankAccountRequest $request, BankAccount $bank, SaveBankAccount $save): RedirectResponse
    {
        return $this->save($request, $bank, $save);
    }

    private function save(BankAccountRequest $request, ?BankAccount $bank, SaveBankAccount $save): RedirectResponse
    {
        Gate::authorize($bank instanceof BankAccount ? 'update' : 'create', $bank ?? BankAccount::class);

        try {
            $save->handle($bank, [
                'account_id' => (int) $request->validated('account_id'), 'name' => (string) $request->validated('name'), 'kind' => (string) $request->validated('kind'),
                'bank_name' => $request->validated('bank_name'), 'account_number' => $request->validated('account_number'), 'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.banks.index')->with('success', __('Bank account saved.'));
    }
}
