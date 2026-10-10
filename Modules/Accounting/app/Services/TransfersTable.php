<?php

namespace Modules\Accounting\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Accounting\Models\FundTransfer;
use Yajra\DataTables\DataTables;

/**
 * The transfers list (server-side DataTable) of the current property.
 */
class TransfersTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'transfer_no', 'title' => __('Transfer')],
            ['data' => 'transfer_date', 'title' => __('Date'), 'searchable' => false],
            ['data' => 'from', 'title' => __('From'), 'orderable' => false, 'searchable' => false],
            ['data' => 'to', 'title' => __('To'), 'orderable' => false, 'searchable' => false],
            ['data' => 'amount', 'title' => __('Amount'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(): JsonResponse
    {
        return $this->dataTables->eloquent(FundTransfer::query()->with(['from', 'to']))
            ->editColumn('transfer_date', fn (FundTransfer $transfer): string => $transfer->transfer_date->format('d M Y'))
            ->addColumn('from', fn (FundTransfer $transfer): string => e($transfer->from->name))
            ->addColumn('to', fn (FundTransfer $transfer): string => e($transfer->to->name))
            ->editColumn('amount', fn (FundTransfer $transfer): string => number_format((float) $transfer->amount, 2))
            ->editColumn('status', fn (FundTransfer $transfer): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $transfer->status]))
            ->addColumn('actions', fn (FundTransfer $transfer): string => '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('accounting.transfers.show', $transfer)).'">'.e(__('Open')).'</a>')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }
}
