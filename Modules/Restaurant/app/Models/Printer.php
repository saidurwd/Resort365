<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\PrinterFactory;
use Modules\Restaurant\Enums\PrinterConnection;
use Modules\Restaurant\Enums\PrinterType;

/**
 * A receipt or kitchen-ticket printer of a property (ARCHITECTURE §5.10.6, Q17): reached through the
 * browser's print dialog in v1; network (ESC/POS) and print-agent printers are kept for later.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property PrinterType $type
 * @property PrinterConnection $connection
 * @property string|null $address
 * @property int $paper_width_mm
 * @property bool $is_active
 */
#[UseFactory(PrinterFactory::class)]
#[Fillable([
    'property_id', 'name', 'type', 'connection', 'address', 'paper_width_mm', 'is_active',
])]
class Printer extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PrinterFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PrinterType::class,
            'connection' => PrinterConnection::class,
            'is_active' => 'boolean',
        ];
    }
}
