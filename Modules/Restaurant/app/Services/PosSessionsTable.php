<?php

namespace Modules\Restaurant\Services;

use App\Support\Tenancy\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Restaurant\Models\PosSession;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of one property's POS sessions, newest first, with the cash variance.
 */
class PosSessionsTable
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
            ['data' => 'outlet', 'title' => __('Outlet / terminal'), 'orderable' => false, 'searchable' => false],
            ['data' => 'cashier', 'title' => __('Opened by'), 'orderable' => false, 'searchable' => false],
            ['data' => 'opening_float', 'title' => __('Float'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'counted_cash', 'title' => __('Counted'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'cash_variance', 'title' => __('Variance'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(int $propertyId): JsonResponse
    {
        $names = collect($this->users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);
        $money = fn (?string $amount): string => $amount !== null ? number_format((float) $amount, 2) : '—';

        return $this->dataTables->eloquent(PosSession::query()->where('property_id', $propertyId)->with(['outlet', 'terminal']))
            ->editColumn('opened_at', fn (PosSession $session): string => app(DisplayTimezone::class)->format($session->opened_at, 'd M Y H:i'))
            ->editColumn('business_date', fn (PosSession $session): string => $session->business_date->format('d M Y'))
            ->addColumn('outlet', fn (PosSession $session): string => e($session->outlet->name.' · '.$session->terminal->name))
            ->addColumn('cashier', fn (PosSession $session): string => e((string) ($names[$session->opened_by] ?? '')))
            ->editColumn('opening_float', fn (PosSession $session): string => $money($session->opening_float))
            ->editColumn('counted_cash', fn (PosSession $session): string => $money($session->counted_cash))
            ->editColumn('cash_variance', fn (PosSession $session): string => $session->cash_variance === null ? '—'
                : '<span class="'.((float) $session->cash_variance < 0 ? 'text-danger' : '').'">'.e($money($session->cash_variance)).'</span>'
                    .($session->manager_approval_id !== null ? ' <i class="bi bi-shield-check text-success" title="'.e(__('Approved by a manager')).'"></i>' : ''))
            ->editColumn('status', fn (PosSession $session): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $session->status]))
            ->addColumn('actions', fn (PosSession $session): string => '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('restaurant.sessions.report', $session)).'">'
                .e($session->isOpen() ? __('X report') : __('Z report')).'</a>')
            ->rawColumns(['cash_variance', 'status', 'actions'])
            ->toJson();
    }
}
