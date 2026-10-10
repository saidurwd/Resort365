<?php

namespace Modules\Accounting\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\CreateTransfer;
use Modules\Accounting\Actions\VoidTransfer;
use Modules\Accounting\DTOs\TransferData;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\BounceChequeRequest;
use Modules\Accounting\Http\Requests\TransferRequest;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\FundTransfer;
use Modules\Accounting\Services\TransfersTable;
use Modules\Core\Contracts\AuditTrail;

/**
 * Accounting → Transfers: money moved between the tenant's cash and bank accounts, posted when saved.
 */
class TransferController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', BankAccount::class);

        return view('accounting::banking.transfers.index', ['columns' => TransfersTable::columns(), 'canManage' => auth()->user()?->can('accounting.bank.manage') ?? false]);
    }

    public function data(TransfersTable $table): JsonResponse
    {
        Gate::authorize('viewAny', BankAccount::class);

        return $table->toJson();
    }

    public function create(): View
    {
        Gate::authorize('create', BankAccount::class);

        return view('accounting::banking.transfers.form', ['banks' => BankAccount::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function store(TransferRequest $request, CreateTransfer $create, PropertyContext $property): RedirectResponse
    {
        Gate::authorize('create', BankAccount::class);
        $propertyId = $property->currentId();

        if ($propertyId === null) {
            return back()->withInput()->with('error', __('Choose a property first.'));
        }

        try {
            $transfer = $create->handle(new TransferData(
                $propertyId, (string) $request->validated('transfer_date'), (int) $request->validated('from_bank_account_id'), (int) $request->validated('to_bank_account_id'),
                (string) $request->validated('amount'), $request->filled('reference') ? (string) $request->validated('reference') : null, $request->filled('notes') ? (string) $request->validated('notes') : null,
            ), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.transfers.show', $transfer)->with('success', __('Transfer :no posted.', ['no' => $transfer->transfer_no]));
    }

    public function show(FundTransfer $transfer, AuditTrail $audit): View
    {
        Gate::authorize('view', BankAccount::class);
        $transfer->load(['from', 'to', 'entry']);

        return view('accounting::banking.transfers.show', ['transfer' => $transfer, 'trail' => $audit->for($transfer), 'canManage' => auth()->user()?->can('accounting.bank.manage') ?? false]);
    }

    public function void(BounceChequeRequest $request, FundTransfer $transfer, VoidTransfer $void): RedirectResponse
    {
        Gate::authorize('void', BankAccount::class);

        try {
            $void->handle($transfer, (string) $request->validated('reason'), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.transfers.show', $transfer)->with('success', __('Transfer voided.'));
    }
}
