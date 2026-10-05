<?php

namespace Modules\Restaurant\Services;

use LogicException;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;

/**
 * The POS terminal (and so the outlet and property) this request comes from, set by
 * EnsurePosTerminal from the device cookie. Scoped to the request.
 */
class PosContext
{
    private ?PosTerminal $terminal = null;

    public function set(PosTerminal $terminal): void
    {
        $this->terminal = $terminal;
    }

    public function terminal(): PosTerminal
    {
        return $this->terminal ?? throw new LogicException('No POS terminal for this request.');
    }

    public function outlet(): Outlet
    {
        return $this->terminal()->outlet;
    }

    public function has(): bool
    {
        return $this->terminal instanceof PosTerminal;
    }
}
