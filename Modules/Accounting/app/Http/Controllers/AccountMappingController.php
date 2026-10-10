<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\SaveAccountMappings;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\AccountMappingRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\AccountMapping;
use Modules\Accounting\Services\AccountResolver;
use Modules\Accounting\Services\MappingCatalog;

/**
 * Accounting → Account mapping: which ledger account each automatic posting uses. A key left on its
 * default follows the chart's system accounts.
 */
class AccountMappingController extends Controller
{
    public function index(MappingCatalog $catalog, AccountResolver $resolver): View
    {
        Gate::authorize('viewAny', Account::class);
        $accounts = Account::query()->where('is_group', false)->where('is_active', true)->orderBy('code')->get();
        $mapped = AccountMapping::query()->pluck('account_id', 'mapping_key')->all();
        $labels = $accounts->mapWithKeys(fn (Account $account): array => [$account->id => $account->label()])->all();

        $rows = collect($catalog->keys())->map(fn (array $row): array => [
            ...$row, 'account_id' => $mapped[$row['key']] ?? null,
            'default_label' => $labels[$resolver->defaultAccountId($row['key'], $row['category']) ?? 0] ?? __('not set'),
        ])->groupBy('group');

        return view('accounting::mappings.index', ['groups' => $rows, 'accounts' => $labels, 'canManage' => auth()->user()?->can('accounting.account.manage') ?? false]);
    }

    public function update(AccountMappingRequest $request, SaveAccountMappings $save): RedirectResponse
    {
        try {
            $save->handle($request->mappings());
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.mappings.index')->with('success', __('Account mapping saved.'));
    }
}
