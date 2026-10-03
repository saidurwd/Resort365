<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Events\GuestsMerged;
use Modules\Guest\Exceptions\CannotMergeGuests;
use Modules\Guest\Models\Guest;

/**
 * Merges a duplicate profile into the one that is kept, in one transaction:
 * - empty details of the kept guest are filled from the duplicate; preferences are combined,
 *   notes appended, the higher VIP level and any blacklist carried over;
 * - the duplicate's ID documents move to the kept guest;
 * - the duplicate is marked merged_into_id and soft-deleted;
 * - GuestsMerged lets other modules move their records (after commit).
 */
class MergeGuests extends Action
{
    private const array FILL_IF_EMPTY = [
        'title', 'last_name', 'email', 'phone', 'nationality_code', 'date_of_birth', 'id_expiry', 'address', 'company_id',
    ];

    /**
     * @throws CannotMergeGuests
     */
    public function handle(Guest $keep, Guest $duplicate): Guest
    {
        if ($keep->is($duplicate)) {
            throw CannotMergeGuests::because(__('Choose two different guests.'));
        }

        if ($keep->merged_into_id !== null || $duplicate->merged_into_id !== null) {
            throw CannotMergeGuests::because(__('One of these profiles was already merged.'));
        }

        $this->transaction(function () use ($keep, $duplicate): void {
            foreach (self::FILL_IF_EMPTY as $field) {
                if (blank($keep->getAttribute($field)) && filled($duplicate->getAttribute($field))) {
                    $keep->setAttribute($field, $duplicate->getAttribute($field));
                }
            }

            if ($keep->id_number_hash === null && $duplicate->id_number_hash !== null) {
                $keep->forceFill(['id_type' => $duplicate->id_type, 'id_number' => $duplicate->id_number, 'id_number_hash' => $duplicate->id_number_hash]);
            }

            $keep->preferences = array_values(array_unique([...($keep->preferences ?? []), ...($duplicate->preferences ?? [])]));
            $keep->notes = trim(implode("\n\n", array_filter([$keep->notes, $duplicate->notes]))) ?: null;
            $keep->marketing_consent = $keep->marketing_consent || $duplicate->marketing_consent;

            if ($duplicate->vip_level->rank() > $keep->vip_level->rank()) {
                $keep->vip_level = $duplicate->vip_level;
            }

            if ($duplicate->is_blacklisted && ! $keep->is_blacklisted) {
                $keep->forceFill([
                    'is_blacklisted' => true, 'blacklist_reason' => $duplicate->blacklist_reason,
                    'blacklisted_at' => $duplicate->blacklisted_at, 'blacklisted_by' => $duplicate->blacklisted_by,
                ]);
            }

            $keep->save();

            // Media paths use the media id, so moving a file is only a change of owner.
            $duplicate->media()->update(['model_id' => $keep->id]);

            $duplicate->forceFill(['merged_into_id' => $keep->id])->save();
            $duplicate->delete();

            activity()->performedOn($keep)->event('updated')
                ->withProperties(['attributes' => ['merged_guest' => $duplicate->full_name.' (#'.$duplicate->id.')']])
                ->log('Guest "'.$duplicate->full_name.'" merged into this profile');

            GuestsMerged::dispatch($keep->tenant_id, $keep->id, $duplicate->id);
        });

        return $keep;
    }
}
