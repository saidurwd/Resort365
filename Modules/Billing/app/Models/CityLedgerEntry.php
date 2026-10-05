<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Billing\Database\Factories\CityLedgerEntryFactory;
use Modules\Billing\Enums\CityLedgerStatus;

/**
 * What a company owes on account (city ledger, ARCHITECTURE §5.9): a folio balance moved there at
 * check-out, due after the company's payment terms. open = amount − paid − credited.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $company_id
 * @property int|null $folio_id
 * @property int|null $invoice_id
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon $posted_on
 * @property Carbon $due_on
 * @property string $description
 * @property string $amount
 * @property string $paid
 * @property string $credited
 * @property CityLedgerStatus $status
 */
#[UseFactory(CityLedgerEntryFactory::class)]
#[Fillable(['property_id', 'company_id', 'folio_id', 'invoice_id', 'reference_type', 'reference_id', 'posted_on', 'due_on', 'description', 'amount', 'paid', 'credited', 'status'])]
class CityLedgerEntry extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CityLedgerEntryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'posted_on' => 'date',
            'due_on' => 'date',
            'amount' => 'decimal:2',
            'paid' => 'decimal:2',
            'credited' => 'decimal:2',
            'status' => CityLedgerStatus::class,
        ];
    }

    public function open(): string
    {
        return bcsub(bcsub($this->amount, $this->paid, 2), $this->credited, 2);
    }
}
