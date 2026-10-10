<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Accounting\Database\Factories\BankAccountFactory;
use Modules\Accounting\Enums\BankAccountKind;

/**
 * A cash or bank account of the tenant (Step 4.4), tied to one ledger account. Only bank accounts take statements.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $account_id
 * @property string $name
 * @property BankAccountKind $kind
 * @property string|null $bank_name
 * @property string|null $account_number
 * @property bool $is_active
 * @property-read Account $account
 */
#[UseFactory(BankAccountFactory::class)]
#[Fillable(['account_id', 'name', 'kind', 'bank_name', 'account_number', 'is_active'])]
class BankAccount extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BankAccountFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => BankAccountKind::class, 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<BankStatement, $this>
     */
    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class)->orderByDesc('statement_to');
    }
}
