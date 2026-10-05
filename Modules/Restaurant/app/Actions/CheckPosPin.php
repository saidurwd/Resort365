<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Facades\RateLimiter;
use Modules\IAM\Contracts\PosPins;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosStaff;

/**
 * The fast user switch on a POS terminal (ARCHITECTURE §10.1): checks that the person may work on
 * this terminal and that their PIN is right; five wrong PINs lock them out on this terminal for 15
 * minutes. The caller signs them in.
 */
class CheckPosPin extends Action
{
    private const int ATTEMPTS = 5;

    public function __construct(
        private readonly PosStaff $staff,
        private readonly PosPins $pins,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(PosTerminal $terminal, int $userId, string $pin): void
    {
        $key = 'pos-pin:'.$terminal->id.':'.$userId;

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw new PosNotAllowed(__('Too many wrong PINs. Try again in :minutes minutes.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]));
        }

        if (! $this->staff->canSignIn($userId, $terminal)) {
            throw new PosNotAllowed(__('You cannot work on this terminal.'));
        }

        if (! $this->pins->matches($userId, $pin)) {
            RateLimiter::hit($key, 15 * 60);

            throw new PosNotAllowed(__('Wrong PIN.'));
        }

        RateLimiter::clear($key);
    }
}
