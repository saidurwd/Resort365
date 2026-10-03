<?php

namespace App\Support\UiKit;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Yajra\DataTables\DataTables;

/**
 * Deterministic sample data for the local UI kit.
 */
class UiKitSamples
{
    private const array GUESTS = [
        'Rahim Uddin', 'Nusrat Jahan', 'Tanvir Ahmed', 'Farzana Akter', 'Sabbir Hossain',
        'Maliha Rahman', 'Arif Chowdhury', 'Sadia Islam', 'Imran Kabir', 'Tahmina Begum',
        'Emily Carter', 'Kenji Watanabe',
    ];

    public function __construct(private readonly DataTables $dataTables) {}

    /**
     * @return array<string, mixed>
     */
    public function forIndex(): array
    {
        return [
            'statuses' => DemoStatus::cases(),
            'cottageOptions' => [
                'family-villa' => __('Family Villa'),
                'honeymoon' => __('Honeymoon Cottage'),
                'lake-view' => __('Lake View Cottage'),
                'hill-top' => __('Hill Top Suite'),
            ],
            'amenityOptions' => [
                'wifi' => __('Wi-Fi'),
                'breakfast' => __('Breakfast'),
                'airport-pickup' => __('Airport pickup'),
                'bonfire' => __('Bonfire'),
                'spa' => __('Spa'),
            ],
            'datatableColumns' => [
                ['data' => 'code', 'title' => __('Reservation')],
                ['data' => 'guest', 'title' => __('Guest')],
                ['data' => 'arrival', 'title' => __('Arrival')],
                ['data' => 'nights', 'title' => __('Nights'), 'className' => 'text-end'],
                ['data' => 'status', 'title' => __('Status'), 'orderable' => false, 'searchable' => false],
                ['data' => 'total', 'title' => __('Total (BDT)'), 'className' => 'text-end'],
            ],
            'attachments' => [
                ['name' => 'passport-rahim-uddin.pdf', 'url' => '#', 'size' => 482_311, 'uploaded_by' => 'Front Desk', 'uploaded_at' => Carbon::parse('2026-09-20 10:15')],
                ['name' => 'booking-confirmation.png', 'url' => '#', 'size' => 96_004, 'uploaded_by' => 'Reservations', 'uploaded_at' => Carbon::parse('2026-09-21 16:40')],
            ],
            'photos' => array_map(fn (array $photo): array => [
                'name' => $photo[0],
                'url' => '#',
                'thumb_url' => 'data:image/svg+xml,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3 2"><rect width="3" height="2" fill="'.$photo[1].'"/></svg>'),
                'delete_url' => '#',
            ], [['family-villa-front.jpg', '#4f8a8b'], ['family-villa-terrace.jpg', '#f4a259'], ['master-bedroom.jpg', '#8cb369'], ['sea-view.jpg', '#5b8e7d']]),
            'approvalSteps' => [
                ['level' => 1, 'role' => __('Purchase Manager'), 'approver' => 'Arif Chowdhury', 'status' => DemoStatus::Confirmed, 'acted_at' => Carbon::parse('2026-09-24 11:05'), 'comment' => __('Within budget.')],
                ['level' => 2, 'role' => __('General Manager'), 'approver' => null, 'status' => DemoStatus::Tentative, 'acted_at' => null, 'comment' => null],
            ],
            'auditEntries' => [
                ['description' => __('Reservation confirmed'), 'causer' => 'Nusrat Jahan', 'at' => Carbon::parse('2026-09-25 09:30'), 'changes' => ['status' => ['tentative', 'confirmed']]],
                ['description' => __('Deposit received'), 'causer' => 'Tanvir Ahmed', 'at' => Carbon::parse('2026-09-24 18:12'), 'changes' => ['paid_amount' => ['0.00', '7500.00']]],
                ['description' => __('Reservation created'), 'causer' => 'Nusrat Jahan', 'at' => Carbon::parse('2026-09-24 17:58'), 'changes' => []],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forPrint(): array
    {
        return [
            'lines' => [
                ['description' => __('Family Villa — 3 nights'), 'quantity' => 3, 'rate' => '9500.00', 'amount' => '28500.00'],
                ['description' => __('Airport pickup'), 'quantity' => 1, 'rate' => '1500.00', 'amount' => '1500.00'],
                ['description' => __('Bonfire dinner'), 'quantity' => 4, 'rate' => '1200.00', 'amount' => '4800.00'],
            ],
            'subtotal' => '34800.00',
            'vat' => '5220.00',
            'total' => '40020.00',
        ];
    }

    public function reservationsTable(): JsonResponse
    {
        return $this->dataTables->collection($this->reservations()->all())
            ->editColumn('status', fn (array $row): string => Blade::render('<x-status-badge :status="$status" />', ['status' => $row['status']]))
            ->rawColumns(['status'])
            ->toJson();
    }

    /**
     * @return Collection<int, array{code: string, guest: string, arrival: string, nights: int, status: DemoStatus, total: string}>
     */
    private function reservations(): Collection
    {
        $statuses = DemoStatus::cases();

        return collect(range(1, 60))->map(fn (int $i): array => [
            'code' => sprintf('RSV-2026-%05d', $i),
            'guest' => self::GUESTS[$i % count(self::GUESTS)],
            'arrival' => Carbon::parse('2026-10-01')->addDays($i % 45)->toDateString(),
            'nights' => $i % 5 + 1,
            'status' => $statuses[$i % count($statuses)],
            'total' => number_format(($i % 5 + 1) * 9500, 2, '.', ''),
        ]);
    }
}
