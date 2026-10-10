<?php

namespace Modules\Accounting\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Accounting\Actions\ChangePeriodStatus;
use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Enums\PeriodStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\FiscalYearRequest;
use Modules\Accounting\Http\Requests\PeriodStatusRequest;
use Modules\Accounting\Models\FiscalPeriod;
use Modules\Accounting\Models\FiscalYear;
use Modules\Core\Contracts\Settings;

/**
 * Accounting → Fiscal periods: fiscal years with their monthly periods, and closing, locking and
 * reopening them.
 */
class FiscalPeriodController extends Controller
{
    public function index(Settings $settings): View
    {
        abort_unless(auth()->user()?->can('accounting.period.view') ?? false, 403);
        $years = FiscalYear::query()->with('periods')->orderByDesc('starts_on')->get();
        $startMonth = (int) $settings->get('accounting.fiscal_year_start_month');
        $next = $years->isEmpty() ? CarbonImmutable::create(now()->year, $startMonth, 1) : CarbonImmutable::parse($years->first()->ends_on)->addDay();

        return view('accounting::periods.index', [
            'years' => $years, 'nextStart' => $next->toDateString(),
            'canManage' => auth()->user()->can('accounting.period.manage'),
            'canReopen' => auth()->user()->can('accounting.period.reopen'),
        ]);
    }

    public function store(FiscalYearRequest $request, CreateFiscalYear $create): RedirectResponse
    {
        try {
            $year = $create->handle((string) $request->validated('starts_on'), $request->validated('name'));
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.periods.index')->with('success', __(':year opened with twelve periods.', ['year' => $year->name]));
    }

    public function update(PeriodStatusRequest $request, FiscalPeriod $period, ChangePeriodStatus $change): RedirectResponse
    {
        try {
            $change->handle($period, PeriodStatus::from((string) $request->validated('status')), (int) $request->user()?->getAuthIdentifier(),
                $request->user()?->can('accounting.period.reopen') ?? false, $request->validated('reason'));
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.periods.index')->with('success', __(':period is now :status.', ['period' => $period->name, 'status' => mb_strtolower(PeriodStatus::from((string) $request->validated('status'))->label())]));
    }
}
