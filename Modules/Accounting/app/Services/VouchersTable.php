<?php

namespace Modules\Accounting\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Accounting\Enums\VoucherType;
use Modules\Accounting\Models\Voucher;
use Yajra\DataTables\DataTables;

/**
 * The vouchers list (server-side DataTable) of the current property, filtered by type.
 */
class VouchersTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'voucher_no', 'title' => __('Voucher')],
            ['data' => 'voucher_date', 'title' => __('Date'), 'searchable' => false],
            ['data' => 'type', 'title' => __('Type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'payee', 'title' => __('Payee')],
            ['data' => 'description', 'title' => __('Description')],
            ['data' => 'amount', 'title' => __('Amount'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(?string $type): JsonResponse
    {
        $query = Voucher::query()->when(VoucherType::tryFrom((string) $type) instanceof VoucherType, fn ($query) => $query->where('type', $type));

        return $this->dataTables->eloquent($query)
            ->editColumn('voucher_date', fn (Voucher $voucher): string => $voucher->voucher_date->format('d M Y'))
            ->editColumn('payee', fn (Voucher $voucher): string => e((string) $voucher->payee))
            ->editColumn('description', fn (Voucher $voucher): string => e($voucher->description))
            ->editColumn('amount', fn (Voucher $voucher): string => number_format((float) $voucher->amount, 2))
            ->editColumn('type', fn (Voucher $voucher): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $voucher->type]))
            ->editColumn('status', fn (Voucher $voucher): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $voucher->status]))
            ->addColumn('actions', fn (Voucher $voucher): string => '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('accounting.vouchers.show', $voucher)).'">'.e(__('Open')).'</a>')
            ->rawColumns(['type', 'status', 'actions'])
            ->toJson();
    }
}
