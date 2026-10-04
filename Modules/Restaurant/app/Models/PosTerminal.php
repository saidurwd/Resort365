<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PosTerminalFactory;

/**
 * A POS device registered at an outlet (ARCHITECTURE §5.10.1): a tablet or till, known by a device token
 * (stored hashed; the plain token is shown once) so sessions, printers and reports are tied to it.
 * The device signs in with its token from Step 3.3 (last_seen_at).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property string $name
 * @property string $device_token
 * @property int|null $receipt_printer_id
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 */
#[UseFactory(PosTerminalFactory::class)]
#[Hidden(['device_token'])]
#[Fillable([
    'property_id', 'outlet_id', 'name', 'device_token', 'receipt_printer_id', 'is_active', 'last_seen_at',
])]
class PosTerminal extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosTerminalFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
