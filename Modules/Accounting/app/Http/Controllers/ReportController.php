<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\DTOs\Report;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\ReportRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\ReportService;
use Modules\Accounting\Services\StatementExport;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Accounting → Financial reports: trial balance, general ledger, profit and loss, balance sheet, cash flow, the
 * USALI departmental report and aging, each with exports. A report with a date range takes from and to; the balance
 * sheet and aging take one date.
 */
class ReportController extends Controller
{
    public const array REPORTS = [
        'trial-balance' => 'Trial balance', 'ledger' => 'General ledger', 'profit-loss' => 'Profit and loss', 'balance-sheet' => 'Balance sheet',
        'cash-flow' => 'Cash flow', 'departmental' => 'USALI departmental report', 'aging-receivable' => 'Receivables aging', 'aging-payable' => 'Payables aging',
    ];

    public function index(): View
    {
        Gate::authorize('viewAny', Account::class);

        return view('accounting::reports.index', ['reports' => self::REPORTS]);
    }

    public function show(ReportRequest $request, string $report, ReportService $reports, StatementExport $export): View|Response|BinaryFileResponse|RedirectResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        Gate::authorize('viewAny', Account::class);
        $filter = $request->filter();

        try {
            $built = match ($report) {
                'trial-balance' => $reports->trialBalance($filter),
                'ledger' => $request->filled('account') ? $reports->generalLedger($filter, (int) $request->validated('account')) : null,
                'profit-loss' => $reports->profitAndLoss($filter),
                'balance-sheet' => $reports->balanceSheet($filter),
                'cash-flow' => $reports->cashFlow($filter),
                'departmental' => $reports->departmental($filter),
                'aging-receivable' => $reports->aging(true, $filter->to, $filter->property),
                default => $reports->aging(false, $filter->to, $filter->property),
            };
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($built instanceof Report && $request->filled('export')) {
            abort_unless($request->user()?->can('accounting.report.export'), 403);

            return $export->download($built, (string) $request->validated('export'), $report.'-'.$filter->to);
        }

        return view('accounting::reports.show', [
            'key' => $report, 'title' => __(self::REPORTS[$report]), 'report' => $built, 'filter' => $filter, 'account' => $request->filled('account') ? (int) $request->validated('account') : null,
            'rangeless' => in_array($report, ['balance-sheet', 'aging-receivable', 'aging-payable'], true),
            'properties' => collect(app(PropertyDirectory::class)->all())->mapWithKeys(fn ($property): array => [(string) $property->id => $property->name])->all(),
            'accounts' => Account::query()->where('is_group', false)->orderBy('code')->get()->mapWithKeys(fn (Account $account): array => [$account->id => $account->label()])->all(),
            'departments' => collect(app(DepartmentDirectory::class)->all())->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all(),
            'canExport' => $request->user()?->can('accounting.report.export') ?? false,
        ]);
    }
}
