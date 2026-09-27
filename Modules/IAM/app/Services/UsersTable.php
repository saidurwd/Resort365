<?php

namespace Modules\IAM\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\IAM\Models\User;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of the current tenant's users.
 */
class UsersTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'name', 'title' => __('Name')],
            ['data' => 'email', 'title' => __('Email')],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'two_factor', 'title' => __('2FA'), 'orderable' => false, 'searchable' => false],
            ['data' => 'last_login_at', 'title' => __('Last sign-in'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(User $actor): JsonResponse
    {
        return $this->dataTables->eloquent(User::query()->select('users.*'))
            ->editColumn('status', fn (User $user): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $user->status]))
            ->addColumn('two_factor', fn (User $user): string => $user->hasTwoFactorEnabled()
                ? '<i class="bi bi-shield-check text-success" title="'.e(__('Enabled')).'"></i>'
                : '<i class="bi bi-shield text-body-tertiary" title="'.e(__('Not enabled')).'"></i>')
            ->editColumn('last_login_at', fn (User $user): string => $user->last_login_at?->format('d M Y H:i') ?? '—')
            ->addColumn('actions', fn (User $user): string => view('iam::users.partials.actions', ['user' => $user, 'actor' => $actor])->render())
            ->rawColumns(['status', 'two_factor', 'actions'])
            ->toJson();
    }
}
