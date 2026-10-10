<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\AccountMappingFactory;

/**
 * Which ledger account a posting key uses for this tenant (ARCHITECTURE §7.1, Step 4.2).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $mapping_key
 * @property int $account_id
 */
#[UseFactory(AccountMappingFactory::class)]
#[Fillable(['mapping_key', 'account_id'])]
class AccountMapping extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AccountMappingFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
