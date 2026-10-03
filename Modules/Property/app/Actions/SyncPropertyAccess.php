<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Modules\Property\Models\Property;

/**
 * Replaces the property assignments of the given users (property id => list of user ids).
 * Users and properties are validated as belonging to the tenant by UpdatePropertyAccessRequest.
 */
class SyncPropertyAccess extends Action
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<int>  $userIds  users shown on the screen (their rows are replaced)
     * @param  array<int, list<int>>  $assignments  property id => user ids with access
     */
    public function handle(array $userIds, array $assignments): void
    {
        $tenantId = $this->context->tenantOrFail()->id;

        $this->transaction(function () use ($userIds, $assignments, $tenantId): void {
            $before = DB::table('property_user')->where('tenant_id', $tenantId)->whereIn('user_id', $userIds)->get(['property_id', 'user_id'])
                ->map(fn (object $row): string => $row->property_id.':'.$row->user_id)->sort()->values()->all();

            DB::table('property_user')->where('tenant_id', $tenantId)->whereIn('user_id', $userIds)->delete();

            $rows = [];
            foreach ($assignments as $propertyId => $users) {
                foreach (array_intersect($users, $userIds) as $userId) {
                    $rows[] = ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()];
                }
            }
            DB::table('property_user')->insert($rows);

            $after = collect($rows)->map(fn (array $row): string => $row['property_id'].':'.$row['user_id'])->sort()->values()->all();

            if ($before !== $after) {
                $names = Property::query()->pluck('code', 'id');
                $describe = fn (array $pairs): string => collect($pairs)->map(function (string $pair) use ($names): string {
                    [$property, $user] = explode(':', $pair);

                    return 'user '.$user.' → '.($names[(int) $property] ?? $property);
                })->implode(', ');

                activity()->event('updated')
                    ->withProperties(['old' => ['access' => $describe($before)], 'attributes' => ['access' => $describe($after)]])
                    ->log('Property access changed');
            }
        });
    }
}
