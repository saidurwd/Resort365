<?php

namespace Modules\Restaurant\Services;

use Illuminate\Support\Facades\DB;
use Modules\IAM\Contracts\UserDirectory;

/**
 * Which outlets a user may work in (ARCHITECTURE §3, rule 5): the outlets assigned to them in
 * outlet_user, or every outlet with restaurant.outlet.access-all. Used by the POS from Step 3.3.
 */
class OutletAccess
{
    public const string ACCESS_ALL = 'restaurant.outlet.access-all';

    public function __construct(
        private readonly UserDirectory $users,
    ) {}

    /**
     * @return list<int>|null the outlet ids, or null for every outlet
     */
    public function outletIds(int $userId): ?array
    {
        if ($this->users->userCan($userId, self::ACCESS_ALL)) {
            return null;
        }

        return DB::table('outlet_user')->where('user_id', $userId)->pluck('outlet_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function canUse(int $userId, int $outletId): bool
    {
        $ids = $this->outletIds($userId);

        return $ids === null || in_array($outletId, $ids, true);
    }

    /**
     * Current assignments of the property's outlets as "outletId:userId" => true.
     *
     * @return array<string, bool>
     */
    public function assignments(int $propertyId): array
    {
        return DB::table('outlet_user')->join('outlets', 'outlets.id', '=', 'outlet_user.outlet_id')->where('outlets.property_id', $propertyId)
            ->get(['outlet_user.outlet_id', 'outlet_user.user_id'])->mapWithKeys(fn (object $row): array => [$row->outlet_id.':'.$row->user_id => true])->all();
    }
}
