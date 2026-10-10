<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\MarkChequeBounced;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\BounceChequeRequest;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\Voucher;
use Modules\Accounting\Services\ChequesTable;

/**
 * Accounting → Cheques: the register of vouchers paid or received by cheque, and bouncing a pending one.
 */
class ChequeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BankAccount::class);

        return view('accounting::banking.cheques', [
            'columns' => ChequesTable::columns(),
            'statuses' => collect(ChequeStatus::cases())->mapWithKeys(fn (ChequeStatus $status): array => [$status->value => $status->label()])->all(),
        ]);
    }

    public function data(Request $request, ChequesTable $table): JsonResponse
    {
        Gate::authorize('viewAny', BankAccount::class);

        return $table->toJson($request->query('status'));
    }

    public function bounce(BounceChequeRequest $request, Voucher $voucher, MarkChequeBounced $bounce): RedirectResponse
    {
        Gate::authorize('update', BankAccount::class);

        try {
            $bounce->handle($voucher, (string) $request->validated('reason'), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.vouchers.show', $voucher)->with('success', __('Cheque marked bounced and the voucher voided.'));
    }
}
