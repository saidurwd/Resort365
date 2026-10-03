<?php

namespace Modules\Reservation\Services;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Blade;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Reservation\Models\Reservation;
use Yajra\DataTables\DataTables;

/**
 * Server-side DataTable of the current property's reservations. Filters: status, source and an
 * arrival date range. The search box matches the start of the reservation number or the guest
 * (through GuestLookup); guest names are fetched for the page in one query.
 */
class ReservationsTable
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
            ['data' => 'code', 'title' => __('Booking')],
            ['data' => 'guest', 'title' => __('Guest'), 'orderable' => false, 'searchable' => false],
            ['data' => 'check_in', 'title' => __('Arrival'), 'searchable' => false],
            ['data' => 'check_out', 'title' => __('Departure'), 'searchable' => false],
            ['data' => 'status', 'title' => __('Status'), 'searchable' => false],
            ['data' => 'payment_status', 'title' => __('Payment'), 'searchable' => false],
            ['data' => 'grand_total', 'title' => __('Total'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'balance_due', 'title' => __('Balance'), 'searchable' => false, 'className' => 'text-end'],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end text-nowrap'],
        ];
    }

    /**
     * @param  array{status?: string|null, source?: string|null, from?: string|null, to?: string|null}  $filters  validated
     */
    public function toJson(array $filters, string $term): JsonResponse
    {
        $query = Reservation::query()->select('reservations.*')
            ->where('property_id', $this->properties->currentId())
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('check_in', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('check_in', '<=', $to));

        $money = fn (string $amount): string => number_format((float) $amount, 2);
        $badge = fn ($status): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $status]);

        $response = $this->dataTables->eloquent($query)
            ->filter(fn (Builder $query): Builder => $this->search($query, trim($term)), false)
            ->editColumn('code', fn (Reservation $reservation): string => '<a href="'.e(route('reservation.bookings.show', $reservation)).'" class="fw-semibold font-monospace">'.e($reservation->code).'</a>')
            ->addColumn('guest', fn (Reservation $reservation): int => $reservation->primary_guest_id)
            ->editColumn('check_in', fn (Reservation $reservation): string => $reservation->check_in->format('d M Y'))
            ->editColumn('check_out', fn (Reservation $reservation): string => $reservation->check_out->format('d M Y'))
            ->editColumn('status', fn (Reservation $reservation): string => $badge($reservation->status))
            ->editColumn('payment_status', fn (Reservation $reservation): string => $badge($reservation->payment_status))
            ->editColumn('grand_total', fn (Reservation $reservation): string => $money($reservation->grand_total))
            ->editColumn('balance_due', fn (Reservation $reservation): string => $money($reservation->balance_due))
            ->addColumn('actions', fn (Reservation $reservation): string => '<a href="'.e(route('reservation.bookings.show', $reservation)).'" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> '.e(__('Open')).'</a>')
            ->rawColumns(['code', 'status', 'payment_status', 'actions'])
            ->toJson();

        return $this->withGuestNames($response);
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return Builder<Reservation>
     */
    private function search(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        // Only guests with a booking here, so a common name cannot crowd out the right guest.
        $bookers = Reservation::query()->where('property_id', $this->properties->currentId())->select('primary_guest_id')->toBase();
        $guestIds = array_map(fn (GuestSummary $guest): int => $guest->id, $this->guests->search($term, 500, $bookers));

        return $query->where(fn (Builder $query) => $query->where('code', 'like', strtoupper($term).'%')->orWhereIn('primary_guest_id', $guestIds));
    }

    /**
     * Replace each row's guest id with the guest's name (escaped), one lookup for the page.
     */
    private function withGuestNames(JsonResponse $response): JsonResponse
    {
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
}
