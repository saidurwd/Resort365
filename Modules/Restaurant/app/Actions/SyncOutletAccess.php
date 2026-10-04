<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Modules\Restaurant\Models\Outlet;

/**
 * Replaces which of the given users work in which outlets of a property (outlet_user), and logs the
 * change. Users and outlets are validated as the tenant's by UpdateOutletAccessRequest.
 */
class SyncOutletAccess extends Action
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<int>  $userIds  users shown on the screen (their rows for this property are replaced)
     * @param  array<int, list<int>>  $assignments  outlet id => user ids
     */
    public function handle(int $propertyId, array $userIds, array $assignments): void
    {
        $tenantId = $this->context->tenantOrFail()->id;
        $outletIds = Outlet::query()->where('property_id', $propertyId)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $this->transaction(function () use ($userIds, $assignments, $tenantId, $outletIds): void {
            /** @param iterable<object|array<string, mixed>> $rows */
            $pairs = function (iterable $rows): array {
                $list = [];

                foreach ($rows as $row) {
                    $row = (array) $row;
                    $list[] = $row['outlet_id'].':'.$row['user_id'];
                }

                sort($list);

                return $list;
            };
            $before = $pairs(DB::table('outlet_user')->whereIn('outlet_id', $outletIds)->whereIn('user_id', $userIds)->get(['outlet_id', 'user_id']));

            DB::table('outlet_user')->whereIn('outlet_id', $outletIds)->whereIn('user_id', $userIds)->delete();
            $rows = [];

            foreach ($assignments as $outletId => $users) {
                if (! in_array($outletId, $outletIds, true)) {
                    continue;
                }

                foreach (array_intersect($users, $userIds) as $userId) {
                    $rows[] = ['tenant_id' => $tenantId, 'outlet_id' => $outletId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()];
                }
            }

            DB::table('outlet_user')->insert($rows);
            $after = $pairs($rows);

            if ($before !== $after) {
                $names = Outlet::query()->pluck('code', 'id');
                $describe = fn (array $list): string => collect($list)->map(function (string $pair) use ($names): string {
                    [$outlet, $user] = explode(':', $pair);

                    return 'user '.$user.' → '.($names[(int) $outlet] ?? $outlet);
                })->implode(', ');

                activity()->event('updated')->withProperties(['old' => ['access' => $describe($before)], 'attributes' => ['access' => $describe($after)]])->log('Outlet access changed');
            }
        });
    }
}
