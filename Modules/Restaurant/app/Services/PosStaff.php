<?php

namespace Modules\Restaurant\Services;

use App\Support\Tenancy\PropertyAccess;
use Illuminate\Support\Facades\Auth;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Restaurant\Models\PosTerminal;

/**
 * Who may sign in on a POS terminal with a PIN (ARCHITECTURE §3.3 rules 5–6): active users with a POS
 * PIN and restaurant.pos.use, who work in the terminal's outlet (OutletAccess) and its property.
 */
class PosStaff
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly PosPins $pins,
        private readonly OutletAccess $outlets,
    ) {}

    /**
     * @return list<UserSummary> by name
     */
    public function forTerminal(PosTerminal $terminal): array
    {
        return array_values(array_filter(collect($this->users->all())->sortBy('name')->values()->all(),
            fn (UserSummary $user): bool => $this->canSignIn($user->id, $terminal, $user)));
    }

    public function canSignIn(int $userId, PosTerminal $terminal, ?UserSummary $summary = null): bool
    {
        $summary ??= collect($this->users->all())->firstWhere('id', $userId);

        if (! $summary instanceof UserSummary || $summary->status !== 'active' || ! $this->pins->has($userId) || ! $this->users->userCan($userId, 'restaurant.pos.use')
            || ! $this->outlets->canUse($userId, $terminal->outlet_id)) {
            return false;
        }

        $user = Auth::guard('web')->getProvider()->retrieveById($userId);

        return $user !== null && array_key_exists($terminal->property_id, app(PropertyAccess::class)->accessibleProperties($user));
    }
}
