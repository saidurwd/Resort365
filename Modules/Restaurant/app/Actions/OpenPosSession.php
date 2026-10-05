<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Models\PosTerminal;

/**
 * Opens a POS session on a terminal with a cash float, on the property's business date (ARCHITECTURE
 * §5.10.11). A terminal has at most one open session.
 */
class OpenPosSession extends Action
{
    public function __construct(
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosTerminal $terminal, int $userId, string $openingFloat): PosSession
    {
        $float = BigDecimal::of($openingFloat)->toScale(2);

        if ($float->isNegative()) {
            throw new PosNotAllowed(__('The float cannot be negative.'));
        }

        try {
            return PosSession::query()->create([
                'property_id' => $terminal->property_id, 'outlet_id' => $terminal->outlet_id, 'pos_terminal_id' => $terminal->id,
                'business_date' => $this->properties->find($terminal->property_id)->businessDate ?? now()->toDateString(),
                'opened_by' => $userId, 'opened_at' => now(), 'opening_float' => (string) $float, 'status' => PosSessionStatus::Open, 'open_terminal_id' => $terminal->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new PosNotAllowed(__('This terminal already has an open session.'));
        }
    }
}
