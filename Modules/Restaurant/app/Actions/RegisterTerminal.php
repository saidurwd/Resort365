<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Str;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;

/**
 * Registers a POS device at an outlet, or gives an existing one a new device token (a lost or
 * replaced tablet). Only the token's hash is stored; the plain token is returned once, to be entered
 * on the device (Step 3.3).
 */
class RegisterTerminal extends Action
{
    /**
     * @return array{PosTerminal, string} the terminal and its plain device token
     */
    public function handle(Outlet $outlet, string $name, ?int $receiptPrinterId = null): array
    {
        $token = $this->token();
        $terminal = PosTerminal::query()->create([
            'property_id' => $outlet->property_id, 'outlet_id' => $outlet->id, 'name' => $name, 'receipt_printer_id' => $receiptPrinterId,
            'device_token' => hash('sha256', $token), 'is_active' => true,
        ]);

        return [$terminal, $token];
    }

    /**
     * @return string the new plain token (the old one stops working)
     */
    public function newToken(PosTerminal $terminal): string
    {
        $token = $this->token();
        $terminal->forceFill(['device_token' => hash('sha256', $token), 'last_seen_at' => null])->save();

        return $token;
    }

    private function token(): string
    {
        return strtoupper(implode('-', str_split(Str::random(16), 4)));
    }
}
