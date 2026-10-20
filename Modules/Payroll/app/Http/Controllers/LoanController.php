<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\Payroll\Actions\CreateLoan;
use Modules\Payroll\Actions\SkipInstallment;
use Modules\Payroll\Actions\WaiveInstallment;
use Modules\Payroll\Enums\LoanKind;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\LoanRequest;
use Modules\Payroll\Http\Requests\WaiveInstallmentRequest;
use Modules\Payroll\Models\Loan;
use Modules\Payroll\Models\LoanInstallment;

/**
 * Payroll → Loans and advances: who owes what, a new loan or advance, and a loan's installments (move one to the end, write one off).
 */
class LoanController extends Controller
{
    public function index(Request $request, EmployeeDirectory $employees): View
    {
        Gate::authorize('viewAny', Loan::class);
        $loans = Loan::query()->with('installments')->orderByDesc('id')->limit(200)->get();
        $names = collect($employees->search('', 500, false))->pluck('name', 'id');

        return view('payroll::loans.index', ['loans' => $loans, 'names' => $names, 'canManage' => $request->user()?->can('payroll.loan.manage') ?? false]);
    }

    public function create(Request $request, EmployeeDirectory $employees): View
    {
        Gate::authorize('create', Loan::class);
        $chosen = $request->filled('employee') ? $employees->find((int) $request->query('employee')) : null;

        return view('payroll::loans.create', ['kinds' => LoanKind::options(), 'methods' => PayMethod::options(), 'chosen' => $chosen, 'today' => now()->toDateString()]);
    }

    public function store(LoanRequest $request, CreateLoan $create): RedirectResponse
    {
        Gate::authorize('create', Loan::class);
        $data = $request->validated();

        try {
            $loan = $create->handle((int) $data['employee_id'], LoanKind::from((string) $data['kind']), (string) $data['principal'], (int) $data['installments'], $data['first_month'].'-01', (string) $data['disbursed_on'],
                PayMethod::from((string) $data['payment_method']), $data['reason'] ?? null, $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.loans.show', $loan)->with('success', __(':no paid out; :count installments scheduled.', ['no' => $loan->loan_no, 'count' => $loan->installments_count]));
    }

    public function show(Request $request, Loan $loan, EmployeeDirectory $employees, AuditTrail $audit): View
    {
        Gate::authorize('view', $loan);

        return view('payroll::loans.show', [
            'loan' => $loan->load('installments'), 'employee' => $employees->find($loan->employee_id), 'canManage' => $request->user()?->can('update', $loan) ?? false, 'trail' => $audit->for($loan),
        ]);
    }

    public function skip(Loan $loan, LoanInstallment $installment, SkipInstallment $skip): RedirectResponse
    {
        Gate::authorize('update', $loan);
        abort_unless($installment->loan_id === $loan->id, 404);

        try {
            $skip->handle($installment);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.loans.show', $loan)->with('success', __('The installment is moved to the end of the loan.'));
    }

    public function waive(WaiveInstallmentRequest $request, Loan $loan, LoanInstallment $installment, WaiveInstallment $waive): RedirectResponse
    {
        Gate::authorize('update', $loan);
        abort_unless($installment->loan_id === $loan->id, 404);

        try {
            $waive->handle($installment, (string) $request->validated('reason'));
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.loans.show', $loan)->with('success', __('The installment is written off.'));
    }
}
