<?php

namespace Modules\Payroll\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Payroll\Actions\AdjustPayslip;
use Modules\Payroll\Actions\RemovePayslipAdjustment;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\AdjustPayslipRequest;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Services\PayslipDocuments;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Payroll → a payslip: its lines and totals, the PDF, and adjustments while the run is open.
 */
class PayslipController extends Controller
{
    public function show(Request $request, Payslip $payslip, PropertyDirectory $properties): View
    {
        Gate::authorize('view', $payslip);
        $payslip->load(['run', 'lines']);

        return view('payroll::payslips.show', [
            'payslip' => $payslip, 'run' => $payslip->run, 'canAdjust' => $payslip->run->isOpen() && ($request->user()?->can('update', $payslip) ?? false), 'subtitle' => $properties->find($payslip->property_id)->name ?? null,
        ]);
    }

    public function pdf(Payslip $payslip, PayslipDocuments $documents): Response
    {
        Gate::authorize('view', $payslip);

        return response($documents->pdf($payslip), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$documents->fileName($payslip).'"']);
    }

    public function adjust(AdjustPayslipRequest $request, Payslip $payslip, AdjustPayslip $adjust): RedirectResponse
    {
        Gate::authorize('update', $payslip);
        $data = $request->validated();

        try {
            $adjust->handle($payslip, ComponentKind::from((string) $data['kind']), (string) $data['name'], (string) $data['amount'], $data['note']);
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.payslips.show', $payslip)->with('success', __('Adjustment added.'));
    }

    public function removeAdjustment(Payslip $payslip, PayslipLine $line, RemovePayslipAdjustment $remove): RedirectResponse
    {
        Gate::authorize('update', $payslip);
        abort_unless($line->payslip_id === $payslip->id, 404);

        try {
            $remove->handle($line);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.payslips.show', $payslip)->with('success', __('Adjustment removed.'));
    }
}
