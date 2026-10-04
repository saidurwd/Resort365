<?php

namespace Modules\Reservation\Services;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Models\Quote;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of the current property's quotes; the search box matches the quote number
 * or the guest. Open quotes past their validity date show as expired.
 */
class QuotesTable
{
    public function __construct(
        private readonly DataTables $dataTables,
        private readonly GuestLookup $guests,
        private readonly PropertyContext $properties,
    ) {}

    /**
     * @return list<array{data: string, title: string, orderable?: bool, searchable?: bool, className?: string}>
     */
    public static function columns(): array
    {
        return [
            ['data' => 'code', 'title' => __('Quote')],
            ['data' => 'guest', 'title' => __('Guest'), 'orderable' => false, 'searchable' => false],
            ['data' => 'check_in', 'title' => __('Arrival'), 'searchable' => false],
            ['data' => 'check_out', 'title' => __('Departure'), 'searchable' => false],
            ['data' => 'grand_total', 'title' => __('Total'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'valid_until', 'title' => __('Valid until'), 'searchable' => false],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    public function toJson(?QuoteStatus $status, string $term): JsonResponse
    {
        $today = now()->toDateString();
        $open = [QuoteStatus::Draft->value, QuoteStatus::Sent->value];

        $query = Quote::query()->select('quotes.*')->where('property_id', $this->properties->currentId())
            ->when($status === QuoteStatus::Expired, fn (Builder $query) => $query->whereIn('status', $open)->where('valid_until', '<', $today))
            ->when($status instanceof QuoteStatus && $status->isOpen(), fn (Builder $query) => $query->where('status', $status?->value)->where('valid_until', '>=', $today))
            ->when($status instanceof QuoteStatus && ! $status->isOpen() && $status !== QuoteStatus::Expired, fn (Builder $query) => $query->where('status', $status?->value));

        $response = $this->dataTables->eloquent($query)
            ->filter(fn (Builder $query): Builder => $this->search($query, trim($term)), false)
            ->editColumn('code', fn (Quote $quote): string => '<a href="'.e(route('reservation.quotes.show', $quote)).'" class="fw-semibold font-monospace">'.e($quote->code).'</a>')
            ->addColumn('guest', fn (Quote $quote): int => $quote->guest_id)
            ->editColumn('check_in', fn (Quote $quote): string => $quote->check_in->format('d M Y'))
            ->editColumn('check_out', fn (Quote $quote): string => $quote->check_out->format('d M Y'))
            ->editColumn('grand_total', fn (Quote $quote): string => number_format((float) $quote->grand_total, 2))
            ->editColumn('valid_until', fn (Quote $quote): string => $quote->valid_until->format('d M Y'))
            ->editColumn('status', fn (Quote $quote): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $quote->currentStatus()]))
            ->addColumn('actions', fn (Quote $quote): string => '<a href="'.e(route('reservation.quotes.show', $quote)).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> '.e(__('Open')).'</a>')
            ->rawColumns(['code', 'status', 'actions'])
            ->toJson();

        /** @var array{data?: list<array<string, mixed>>} $payload */
        $payload = $response->getData(true);
        $rows = $payload['data'] ?? [];
        $names = $this->guests->names(array_map(fn (array $row): int => (int) $row['guest'], $rows));

        foreach ($rows as $index => $row) {
            $rows[$index]['guest'] = e($names[(int) $row['guest']] ?? '—');
        }

        $payload['data'] = $rows;

        return $response->setData($payload);
    }

    /**
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    private function search(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        $guests = Quote::query()->where('property_id', $this->properties->currentId())->select('guest_id')->toBase();
        $guestIds = array_map(fn (GuestSummary $guest): int => $guest->id, $this->guests->search($term, 500, $guests));

        return $query->where(fn (Builder $query) => $query->where('code', 'like', strtoupper($term).'%')->orWhereIn('guest_id', $guestIds));
    }
}
