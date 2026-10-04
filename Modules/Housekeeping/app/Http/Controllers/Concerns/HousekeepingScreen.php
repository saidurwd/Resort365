<?php

namespace Modules\Housekeeping\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Property\DTOs\RoomSummary;

/**
 * Housekeeping screens work on the current property, on its business date.
 */
trait HousekeepingScreen
{
    protected function property(): PropertySummary
    {
        $id = app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));

        return app(PropertyDirectory::class)->find($id) ?? abort(404);
    }

    /**
     * @return array<int, string> room id => "Room 101"
     */
    protected function roomOptions(int $propertyId): array
    {
        return collect(app(InventoryCatalog::class)->rooms($propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive)->sortBy('number', SORT_NATURAL)
            ->mapWithKeys(fn (RoomSummary $room): array => [$room->id => __('Room :number', ['number' => $room->number])])->all();
    }

    /**
     * Active users with a permission, by name.
     *
     * @return array<int, string> user id => name
     */
    protected function staffWith(string $permission): array
    {
        $users = app(UserDirectory::class);

        return collect($users->all())->filter(fn (UserSummary $user): bool => $user->status === 'active' && $users->userCan($user->id, $permission))
            ->sortBy('name')->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name])->all();
    }

    /**
     * @return array<int, string> user id => name, for showing who did what
     */
    protected function userNames(): array
    {
        return collect(app(UserDirectory::class)->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name])->all();
    }

    protected function userId(): ?int
    {
        $id = auth()->id();

        return $id !== null ? (int) $id : null;
    }
}
