<?php

namespace Modules\Guest\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Models\Guest;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of guests. The search box uses GuestSearch (indexed prefix search)
 * instead of the DataTables default LIKE '%…%' on every column.
 */
class GuestsTable
{
    public const array FILTERS = ['all', 'vip', 'blacklisted'];

    public function __construct(
        private readonly DataTables $dataTables,
        private readonly GuestSearch $search,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'first_name', 'title' => __('Guest')],
            ['data' => 'phone', 'title' => __('Phone')],
            ['data' => 'email', 'title' => __('Email')],
            ['data' => 'nationality_code', 'title' => __('Nationality'), 'searchable' => false],
            ['data' => 'company', 'title' => __('Company'), 'orderable' => false, 'searchable' => false],
            ['data' => 'vip_level', 'title' => __('VIP'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(string $filter, string $term): JsonResponse
    {
        $query = Guest::query()->select('guests.*')->with('company')
            ->when($filter === 'vip', fn (Builder $query) => $query->where('vip_level', '!=', VipLevel::None->value))
            ->when($filter === 'blacklisted', fn (Builder $query) => $query->where('is_blacklisted', true));

        return $this->dataTables->eloquent($query)
            ->filter(fn (Builder $query): Builder => $this->search->apply($query, $term), false)
            ->editColumn('first_name', fn (Guest $guest): string => view('guest::guests.partials.name', ['guest' => $guest])->render())
            // Columns not listed in rawColumns() are escaped by the DataTables package.
            ->editColumn('phone', fn (Guest $guest): string => $guest->phone ?? '—')
            ->editColumn('email', fn (Guest $guest): string => $guest->email ?? '—')
            ->editColumn('nationality_code', fn (Guest $guest): string => $guest->nationality_code ?? '—')
            ->addColumn('company', fn (Guest $guest): string => $guest->company->name ?? '—')
            ->editColumn('vip_level', fn (Guest $guest): string => $guest->vip_level === VipLevel::None ? '' : Blade::render('<x-status-badge :status="$status" />', ['status' => $guest->vip_level]))
            ->addColumn('actions', fn (Guest $guest): string => '<a href="'.e(route('guest.guests.show', $guest)).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> '.e(__('Open')).'</a>')
            ->rawColumns(['first_name', 'vip_level', 'actions'])
            ->toJson();
    }
}
