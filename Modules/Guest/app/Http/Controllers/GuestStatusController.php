<?php

namespace Modules\Guest\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Guest\Actions\BlacklistGuest;
use Modules\Guest\Actions\ClearBlacklist;
use Modules\Guest\Actions\MergeGuests;
use Modules\Guest\Exceptions\CannotMergeGuests;
use Modules\Guest\Http\Requests\BlacklistGuestRequest;
use Modules\Guest\Http\Requests\MergeGuestsRequest;
use Modules\Guest\Models\Guest;

/**
 * Blacklist and merge, from the guest page.
 */
class GuestStatusController extends Controller
{
    public function blacklist(BlacklistGuestRequest $request, Guest $guest, BlacklistGuest $blacklist): RedirectResponse
    {
        $user = $request->user();
        $blacklist->handle($guest, (string) $request->string('reason'), $user !== null ? (int) $user->getAuthIdentifier() : null);

        return back()->with('warning', __('":name" is now blacklisted.', ['name' => $guest->full_name]));
    }

    public function clearBlacklist(Guest $guest, ClearBlacklist $clear): RedirectResponse
    {
        Gate::authorize('blacklist', $guest);
        $clear->handle($guest);

        return back()->with('success', __('":name" is no longer blacklisted.', ['name' => $guest->full_name]));
    }

    public function merge(MergeGuestsRequest $request, Guest $guest, MergeGuests $merge): RedirectResponse
    {
        $duplicate = Guest::query()->findOrFail($request->integer('duplicate_id'));

        try {
            $merge->handle($guest, $duplicate);
        } catch (CannotMergeGuests $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('guest.guests.show', $guest)->with('success', __('":duplicate" was merged into this profile.', ['duplicate' => $duplicate->full_name]));
    }
}
