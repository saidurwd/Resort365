<?php

namespace Modules\Accounting\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Models\JournalEntry;
use Yajra\DataTables\DataTables;

/**
 * The journal entries list (server-side DataTable): number, date, description, total, status; filtered by
 * status and by date range.
 */
class JournalEntriesTable
{
    public function __construct(
        private readonly DataTables $dataTables,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'entry_no', 'title' => __('Entry')],
            ['data' => 'entry_date', 'title' => __('Date'), 'searchable' => false],
            ['data' => 'description', 'title' => __('Description')],
            ['data' => 'reference', 'title' => __('Reference')],
            ['data' => 'total', 'title' => __('Total'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'status', 'title' => __('Status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end'],
        ];
    }

    public function toJson(?string $status, ?string $from, ?string $to): JsonResponse
    {
        $query = JournalEntry::query()
            ->when(JournalStatus::tryFrom((string) $status) instanceof JournalStatus, fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->where('entry_date', '>=', $from))
            ->when($to, fn ($query) => $query->where('entry_date', '<=', $to));

        return $this->dataTables->eloquent($query)
            ->editColumn('entry_no', fn (JournalEntry $entry): string => e($entry->entry_no ?? '—'))
            ->editColumn('entry_date', fn (JournalEntry $entry): string => $entry->entry_date->format('d M Y'))
            ->editColumn('reference', fn (JournalEntry $entry): string => e((string) $entry->reference))
            ->editColumn('total', fn (JournalEntry $entry): string => number_format((float) $entry->total, 2))
            ->editColumn('status', fn (JournalEntry $entry): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $entry->status])
                .($entry->reverses_id !== null ? ' <span class="badge text-bg-light border">'.e(__('reversal')).'</span>' : ''))
            ->addColumn('actions', fn (JournalEntry $entry): string => '<a class="btn btn-sm btn-outline-secondary" href="'.e(route('accounting.journals.show', $entry)).'">'.e(__('Open')).'</a>')
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }
}
