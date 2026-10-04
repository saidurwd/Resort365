<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Database\Factories\FolioFactory;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;

/**
 * A bill of a reservation (ARCHITECTURE §5.9): its charges, payments and adjustments. balance is
 * what is owed (charges less payments), kept in step by FolioLedger. Folios are never deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $reservation_id
 * @property string $folio_no
 * @property FolioType $type
 * @property BillTo $bill_to_type
 * @property int|null $bill_to_id
 * @property string $name
 * @property FolioStatus $status
 * @property string $currency_code
 * @property string $balance
 * @property-read Collection<int, FolioLine> $lines
 */
#[UseFactory(FolioFactory::class)]
#[Fillable(['property_id', 'reservation_id', 'folio_no', 'type', 'bill_to_type', 'bill_to_id', 'name', 'status', 'currency_code', 'balance'])]
class Folio extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<FolioFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FolioType::class,
            'bill_to_type' => BillTo::class,
            'status' => FolioStatus::class,
            'balance' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<FolioLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(FolioLine::class)->orderBy('posting_date')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === FolioStatus::Open;
    }
}
