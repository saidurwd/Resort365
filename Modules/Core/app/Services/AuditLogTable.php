<?php

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Modules\Core\Models\Activity;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of the tenant's audit log.
 */
class AuditLogTable
{
    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'created_at', 'title' => __('When'), 'searchable' => false, 'className' => 'text-nowrap'],
            ['data' => 'causer', 'title' => __('By'), 'orderable' => false, 'searchable' => false],
            ['data' => 'event', 'title' => __('Event')],
            ['data' => 'subject', 'title' => __('Record'), 'orderable' => false, 'searchable' => false],
            ['data' => 'description', 'title' => __('Description')],
            ['data' => 'changes', 'title' => __('Changes'), 'orderable' => false, 'searchable' => false],
        ];
    }

    public function toJson(?string $event = null): JsonResponse
    {
        $query = Activity::query()->with('causer')->select('activity_log.*')
            ->when($event, fn (Builder $query, string $event): Builder => $query->where('event', $event));

        return $this->dataTables->eloquent($query)
            ->editColumn('created_at', fn (Activity $activity): string => $activity->created_at?->format('d M Y H:i') ?? '')
            ->addColumn('causer', fn (Activity $activity): string => e($activity->causer instanceof Model ? (string) $activity->causer->getAttribute('name') : __('System')))
            ->addColumn('subject', fn (Activity $activity): string => e(Str::headline(class_basename((string) $activity->subject_type))).($activity->subject_id ? ' #'.$activity->subject_id : ''))
            ->addColumn('changes', fn (Activity $activity): string => view('core::audit.partials.changes', ['changes' => AuditTrailService::changes($activity)])->render())
            ->rawColumns(['changes'])
            ->toJson();
    }
}
