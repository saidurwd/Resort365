<?php

namespace Modules\Accounting\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Http\Requests\BudgetReportRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Services\BudgetReport;
use Modules\Accounting\Services\StatementExport;
use Modules\Property\Contracts\DepartmentDirectory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Accounting → Budgets → Budget against actual: by account for a run of months, or by month, with the exports of the financial
 * reports (Step 7.2).
 */
class BudgetReportController extends Controller
{
    public function show(BudgetReportRequest $request, Budget $budget, BudgetReport $report, StatementExport $export, DepartmentDirectory $departments): View|Response|BinaryFileResponse
    {
        Gate::authorize('view', $budget);
        $budget->load('fiscalYear.periods');
        $view = (string) $request->validated('view', 'accounts');
        $through = $this->monthOfToday($budget);
        $from = (int) $request->validated('from', 1);
        $to = (int) $request->validated('to', $through);
        $department = $request->filled('department') ? (int) $request->validated('department') : null;
        $account = $request->filled('account') ? (int) $request->validated('account') : null;
        $built = $view === 'months' ? $report->byMonth($budget, $account, $department) : $report->byAccount($budget, $from, $to, $department);

        if ($request->filled('export')) {
            abort_unless($request->user()?->can('accounting.report.export'), 403);

            return $export->download($built, (string) $request->validated('export'), 'budget-'.$budget->fiscalYear->name.'-'.$view);
        }

        return view('accounting::budgets.report', [
            'budget' => $budget, 'report' => $built, 'view' => $view, 'from' => $from, 'to' => $to, 'department' => $department, 'account' => $account,
            'months' => $budget->fiscalYear->periods->pluck('name', 'number')->all(), 'departments' => collect($departments->all())->mapWithKeys(fn ($d): array => [$d->id => $d->name])->all(),
            'accounts' => Account::query()->whereIn('id', $budget->lines()->pluck('account_id'))->orderBy('code')->get()->mapWithKeys(fn (Account $a): array => [$a->id => $a->label()])->all(),
            'canExport' => $request->user()?->can('accounting.report.export') ?? false,
        ]);
    }

    /** The number of the fiscal period today falls in, or 12 once the year is over, or 1 before it begins. */
    private function monthOfToday(Budget $budget): int
    {
        $today = CarbonImmutable::today();
        $period = $budget->fiscalYear->periods->first(fn ($p): bool => $p->starts_on->lte($today) && $p->ends_on->gte($today));

        return $period->number ?? ($today->gt($budget->fiscalYear->ends_on) ? 12 : 1);
    }
}
