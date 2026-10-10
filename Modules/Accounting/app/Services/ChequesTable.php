<?php

namespace Modules\Accounting\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Accounting\Enums\ChequeStatus;
use Modules\Accounting\Models\Voucher;
use Yajra\DataTables\DataTables;

/**
 * The cheque register (server-side DataTable): vouchers paid or received by cheque, filtered by status.
 */
class ChequesTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'cheque_no', 'title' => __('Cheque')],
            ['data' => 'cheque_date', 'title' => __('Cheque date'), 'searchable' => false],
            ['data' => 'direction', 'title' => __('Direction'), 'orderable' => false, 'searchable' => false],
            ['data' => 'bank', 'title' => __('Bank account'), 'orderable' => false, 'searchable' => false],
            ['data' => 'payee', 'title' => __('Party')],
            ['data' => 'amount', 'title' => __('Amount'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'cheque_status', 'title' => __('Status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(?string $status): JsonResponse
    {
        $query = Voucher::query()->with('cashAccount')->whereNotNull('cheque_no')->when(ChequeStatus::tryFrom((string) $status) instanceof ChequeStatus, fn ($query) => $query->where('cheque_status', $status));

        return $this->dataTables->eloquent($query)
            ->editColumn('cheque_date', fn (Voucher $voucher): string => $voucher->cheque_date?->format('d M Y') ?? '')
            ->addColumn('direction', fn (Voucher $voucher): string => e($voucher->type->value === 'income' ? __('Received') : __('Issued')))
            ->addColumn('bank', fn (Voucher $voucher): string => e($voucher->cashAccount->label()))
            ->editColumn('payee', fn (Voucher $voucher): string => e((string) $voucher->payee))
            ->editColumn('amount', fn (Voucher $voucher): string => number_format((float) $voucher->amount, 2))
            ->editColumn('cheque_status', fn (Voucher $voucher): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $voucher->cheque_status]))
            ->addColumn('actions', fn (Voucher $voucher): string => '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('accounting.vouchers.show', $voucher)).'">'.e(__('Open')).'</a>')
            ->rawColumns(['cheque_status', 'actions'])
            ->toJson();
    }
}
