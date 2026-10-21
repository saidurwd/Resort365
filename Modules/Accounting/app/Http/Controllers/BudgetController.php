<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\CopyBudgetFromActuals;
use Modules\Accounting\Actions\DeleteBudget;
use Modules\Accounting\Actions\SaveBudget;
use Modules\Accounting\Actions\SaveBudgetLines;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\BudgetCopyRequest;
use Modules\Accounting\Http\Requests\BudgetLinesRequest;
use Modules\Accounting\Http\Requests\BudgetRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FiscalYear;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Accounting → Budgets: a year's budget for a property or the whole company, and its lines by account, department and month
 * (Step 7.2).
 */
class BudgetController extends Controller
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly DepartmentDirectory $departments,
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Budget::class);
        $names = collect($this->properties->all())->pluck('name', 'id');

        return view('accounting::budgets.index', [
            'budgets' => Budget::query()->with('fiscalYear')->withCount('lines')->orderByDesc('fiscal_year_id')->orderBy('property_id')->get(), 'properties' => $names,
            'canManage' => auth()->user()?->can('accounting.budget.manage') ?? false,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Budget::class);

        return view('accounting::budgets.create', [
            'years' => FiscalYear::query()->orderByDesc('starts_on')->get()->mapWithKeys(fn (FiscalYear $year): array => [$year->id => $year->name])->all(),
            'properties' => collect($this->properties->all())->mapWithKeys(fn ($property): array => [$property->id => $property->name])->all(),
        ]);
    }

    public function store(BudgetRequest $request, SaveBudget $save): RedirectResponse
    {
        Gate::authorize('create', Budget::class);

        try {
            $budget = $save->handle(null, $request->validated(), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.budgets.show', $budget)->with('success', __('The budget is made. Add its lines, or copy them from a year\'s actuals.'));
    }

    public function show(Budget $budget): View
    {
        Gate::authorize('view', $budget);
        $budget->load('fiscalYear.periods');
        $lines = $budget->lines()->get()->map(fn ($line): array => ['account_id' => $line->account_id, 'department_id' => $line->department_id, 'months' => $line->months()])->all();

        return view('accounting::budgets.show', [
            'budget' => $budget, 'lines' => $lines, 'scope' => $budget->property_id === null ? __('All properties') : ($this->properties->find($budget->property_id)->name ?? '#'.$budget->property_id),
            'accounts' => Account::query()->where('is_group', false)->where('is_active', true)->whereIn('type', [AccountType::Income->value, AccountType::Expense->value])->orderBy('code')->get()
                ->map(fn (Account $account): array => ['id' => $account->id, 'label' => $account->label(), 'type' => $account->type->value])->all(),
            'departments' => collect($this->departments->all())->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all(),
            'years' => FiscalYear::query()->whereKeyNot($budget->fiscal_year_id)->orderByDesc('starts_on')->get()->mapWithKeys(fn (FiscalYear $year): array => [$year->id => $year->name])->all(),
            'canManage' => auth()->user()?->can('update', $budget) ?? false,
        ]);
    }

    public function update(BudgetRequest $request, Budget $budget, SaveBudget $save): RedirectResponse
    {
        Gate::authorize('update', $budget);
        $save->handle($budget, $request->validated());

        return to_route('accounting.budgets.show', $budget)->with('success', __('The budget is saved.'));
    }

    public function saveLines(BudgetLinesRequest $request, Budget $budget, SaveBudgetLines $save): RedirectResponse
    {
        Gate::authorize('update', $budget);
        $lines = array_values(array_map(fn (array $line): array => [
            'account_id' => (int) $line['account_id'], 'department_id' => isset($line['department_id']) && $line['department_id'] !== '' ? (int) $line['department_id'] : null,
            'months' => array_map(fn (mixed $amount): string => ($amount === null || $amount === '') ? '0' : (string) $amount, array_values($line['months'])),
        ], (array) $request->validated('lines', [])));

        try {
            $count = $save->handle($budget, $lines);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.budgets.show', $budget)->with('success', trans_choice(':count line saved.|:count lines saved.', $count));
    }

    public function copy(BudgetCopyRequest $request, Budget $budget, CopyBudgetFromActuals $copy): RedirectResponse
    {
        Gate::authorize('update', $budget);

        try {
            $count = $copy->handle($budget, FiscalYear::query()->findOrFail((int) $request->validated('from_year_id')), (string) $request->validated('change'));
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.budgets.show', $budget)->with('success', __(':count lines copied from the actuals. Check them and adjust.', ['count' => $count]));
    }

    public function destroy(Budget $budget, DeleteBudget $delete): RedirectResponse
    {
        Gate::authorize('delete', $budget);
        $delete->handle($budget);

        return to_route('accounting.budgets.index')->with('success', __('The budget is deleted.'));
    }
}
