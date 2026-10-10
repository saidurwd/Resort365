<?php

namespace Modules\Accounting\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Accounting\Actions\CreateVoucher;
use Modules\Accounting\Actions\VoidVoucher;
use Modules\Accounting\DTOs\VoucherData;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Http\Requests\VoidVoucherRequest;
use Modules\Accounting\Http\Requests\VoucherRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\Voucher;
use Modules\Accounting\Services\VouchersTable;
use Modules\Core\Contracts\AuditTrail;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * Accounting → Vouchers: quick income and expense vouchers of the current property, posted when saved.
 */
class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Voucher::class);

        return view('accounting::vouchers.index', [
            'columns' => VouchersTable::columns(), 'canCreate' => $request->user()?->can('accounting.voucher.create') ?? false,
            'types' => collect(VoucherType::cases())->mapWithKeys(fn (VoucherType $type): array => [$type->value => $type->label()])->all(),
        ]);
    }

    public function data(Request $request, VouchersTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Voucher::class);

        return $table->toJson($request->query('type'));
    }

    public function create(string $type): View
    {
        Gate::authorize('create', Voucher::class);
        $kind = VoucherType::tryFrom($type) ?? abort(404);
        $postable = fn (AccountType $accountType) => Account::query()->where('type', $accountType->value)->where('is_group', false)->where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (Account $account): array => [$account->id => $account->label()])->all();

        return view('accounting::vouchers.form', [
            'type' => $kind, 'accounts' => $postable($kind->accountType()), 'cashAccounts' => $postable(AccountType::Asset),
            'departments' => collect(app(DepartmentDirectory::class)->all())->mapWithKeys(fn (DepartmentSummary $department): array => [$department->id => $department->name])->all(),
        ]);
    }

    public function store(VoucherRequest $request, CreateVoucher $create, PropertyContext $property): RedirectResponse
    {
        Gate::authorize('create', Voucher::class);
        $propertyId = $property->currentId();

        if ($propertyId === null) {
            return back()->withInput()->with('error', __('Choose a property first.'));
        }

        try {
            $voucher = $create->handle(new VoucherData(
                VoucherType::from((string) $request->validated('type')), $propertyId, (string) $request->validated('voucher_date'), (int) $request->validated('account_id'),
                (int) $request->validated('cash_account_id'), (string) $request->validated('amount'), (string) $request->validated('description'),
                (string) ($request->validated('tax_amount') ?: '0'), $request->filled('department_id') ? (int) $request->validated('department_id') : null,
                $request->filled('payee') ? (string) $request->validated('payee') : null, $request->filled('reference') ? (string) $request->validated('reference') : null,
                $request->filled('cheque_no') ? (string) $request->validated('cheque_no') : null, $request->filled('cheque_date') ? (string) $request->validated('cheque_date') : null,
            ), $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('accounting.vouchers.show', $voucher)->with('success', __('Voucher :no posted.', ['no' => $voucher->voucher_no]));
    }

    public function show(Voucher $voucher, AuditTrail $audit, UserDirectory $users): View
    {
        Gate::authorize('view', $voucher);
        $voucher->load(['account', 'cashAccount', 'entry']);

        return view('accounting::vouchers.show', [
            'voucher' => $voucher, 'trail' => $audit->for($voucher),
            'names' => collect($users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]),
            'department' => $voucher->department_id !== null ? app(DepartmentDirectory::class)->find($voucher->department_id)?->name : null,
        ]);
    }

    public function void(VoidVoucherRequest $request, Voucher $voucher, VoidVoucher $void): RedirectResponse
    {
        Gate::authorize('void', $voucher);

        try {
            $void->handle($voucher, (string) $request->validated('reason'), null, $request->user()?->id);
        } catch (AccountingRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('accounting.vouchers.show', $voucher)->with('success', __('Voucher voided.'));
    }
}
