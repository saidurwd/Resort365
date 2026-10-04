<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Housekeeping\Actions\AssignTask;
use Modules\Housekeeping\Actions\BlockRoom;
use Modules\Housekeeping\Actions\CreateDailyTasks;
use Modules\Housekeeping\Actions\PrepareRoomsAfterDeparture;
use Modules\Housekeeping\Actions\RecordFoundItem;
use Modules\Housekeeping\Actions\ReportWorkOrder;
use Modules\Housekeeping\Actions\SaveSchedule;
use Modules\Housekeeping\Actions\UpdateWorkOrder;
use Modules\Housekeeping\DTOs\WorkOrderData;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\RoomSummary;

/**
 * Housekeeping demo (Step 2.7) on the business date: room 702 out of order next week (bathroom
 * renovation), rooms 701 and 801 left this morning (dirty, departure cleans for housekeeping@),
 * the stayover round for rooms in house, an urgent AC fault in 502 being fixed by maintenance@, a
 * shower leak reported in 401, AC servicing every 90 days, and two items in lost & found.
 */
final class DemoHousekeeping
{
    public static function seed(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            // Seeding twice keeps the first run's records.
            if (RoomBlock::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $today = CarbonImmutable::parse(app(PropertyDirectory::class)->find($propertyId)->businessDate ?? now()->toDateString());
            $rooms = collect(app(InventoryCatalog::class)->rooms($propertyId))->keyBy(fn (RoomSummary $room): string => $room->number);
            $users = collect(app(UserDirectory::class)->all())->keyBy(fn (UserSummary $user): string => $user->email);
            $housekeeper = $users->get('housekeeping@'.$domain)?->id;
            $technician = $users->get('maintenance@'.$domain)?->id;

            if ($rooms->has('702')) {
                BlockRoom::make()->handle($propertyId, $rooms['702']->id, BlockType::OutOfOrder, $today->addDays(7)->toDateString(), $today->addDays(14)->toDateString(),
                    'Bathroom renovation', $technician);
            }

            $left = $rooms->only(['701', '801'])->map(fn (RoomSummary $room): int => $room->id)->values()->all();
            PrepareRoomsAfterDeparture::make()->handle($propertyId, $left, null, 'Guests left this morning');
            CreateDailyTasks::make()->handle($propertyId, $today->toDateString());
            AssignTask::make()->handle(HousekeepingTask::query()->where('property_id', $propertyId)->pluck('id')->map(fn ($id): int => (int) $id)->all(), $housekeeper);

            if ($rooms->has('502')) {
                $ac = ReportWorkOrder::make()->handle(new WorkOrderData($propertyId, 'AC not cooling', WorkOrderCategory::AirConditioning, WorkOrderPriority::Urgent,
                    $rooms['502']->id, description: 'Guest reports warm air from the AC since last night.', reportedBy: $users->get('frontdesk@'.$domain)?->id));
                UpdateWorkOrder::make()->handle($ac, WorkOrderStatus::InProgress, WorkOrderPriority::Urgent, $technician, '0', '0', null);
            }

            if ($rooms->has('401')) {
                ReportWorkOrder::make()->handle(new WorkOrderData($propertyId, 'Shower leaking', WorkOrderCategory::Plumbing, WorkOrderPriority::Normal,
                    $rooms['401']->id, description: 'Water drips from the shower head when turned off.', reportedBy: $housekeeper));
            }

            SaveSchedule::make()->handle($propertyId, null, ['title' => 'AC servicing', 'category' => WorkOrderCategory::AirConditioning->value, 'location' => 'All rooms',
                'interval_days' => 90, 'next_due_on' => $today->addDays(12)->toDateString(), 'assigned_to' => $technician]);

            RecordFoundItem::make()->handle($propertyId, ['found_on' => $today->subDays(3)->toDateString(), 'found_at' => 'Pool deck',
                'description' => 'Black sunglasses (Ray-Ban)', 'stored_at' => 'Front office safe'], $housekeeper);
            RecordFoundItem::make()->handle($propertyId, ['found_on' => $today->toDateString(), 'room_id' => $rooms->get('801')?->id, 'found_at' => 'Bedside table',
                'description' => 'Phone charger (USB-C)', 'stored_at' => 'Housekeeping office'], $housekeeper);
        });
    }
}
