<?php

namespace Modules\Billing\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Billing\Models\CashierShift;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's cashier shifts, newest first, with the variance.
 */
class ShiftsTable
{
    public function __construct(
        private readonly DataTables $dataTables,
        private readonly UserDirectory $users,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'opened_at', 'title' => __('Opened'), 'searchable' => false],
            ['data' => 'business_date', 'title' => __('Business date'), 'searchable' => false],
            ['data' => 'cashier', 'title' => __('Cashier'), 'orderable' => false, 'searchable' => false],
            ['data' => 'opening_float', 'title' => __('Float'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'expected_cash', 'title' => __('Expected'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'counted_cash', 'title' => __('Counted'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'cash_variance', 'title' => __('Variance'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(int $propertyId): JsonResponse
    {
        $names = collect($this->users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);
        $money = fn (?string $amount): string => $amount !== null ? number_format((float) $amount, 2) : '—';

        return $this->dataTables->eloquent(CashierShift::query()->where('property_id', $propertyId))
            ->editColumn('opened_at', fn (CashierShift $shift): string => $shift->opened_at->format('d M Y H:i'))
            ->editColumn('business_date', fn (CashierShift $shift): string => $shift->business_date->format('d M Y'))
            ->addColumn('cashier', fn (CashierShift $shift): string => (string) ($names[$shift->user_id] ?? '#'.$shift->user_id))
            ->editColumn('opening_float', fn (CashierShift $shift): string => $money($shift->opening_float))
            ->editColumn('expected_cash', fn (CashierShift $shift): string => $money($shift->expected_cash))
            ->editColumn('counted_cash', fn (CashierShift $shift): string => $money($shift->counted_cash))
            ->editColumn('cash_variance', fn (CashierShift $shift): string => $shift->cash_variance === null ? '—'
                : '<span class="'.((float) $shift->cash_variance < 0 ? 'text-danger' : ((float) $shift->cash_variance > 0 ? 'text-warning-emphasis' : '')).'">'.e($money($shift->cash_variance)).'</span>')
            ->editColumn('status', fn (CashierShift $shift): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $shift->status]))
            ->addColumn('actions', fn (CashierShift $shift): string => '<a href="'.e(route('billing.shifts.show', $shift)).'" class="btn btn-sm btn-outline-secondary">'.e(__('Report')).'</a>')
            ->rawColumns(['cash_variance', 'status', 'actions'])
            ->toJson();
    }
}
