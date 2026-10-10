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
use Modules\Accounting\Database\Factories\AccountFactory;
use Modules\Accounting\Enums\AccountType;

/**
 * A ledger account of the chart (ARCHITECTURE §5.14): a tree by parent, of one type; group accounts are
 * headers and take no postings. A system account (system_key) is one automatic postings use (Step 4.2):
 * it cannot be deleted. Accounts with postings are deactivated, not deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property bool $is_group
 * @property bool $is_active
 * @property string|null $system_key
 * @property string|null $usali_department
 * @property int $sort_order
 * @property string|null $description
 */
#[UseFactory(AccountFactory::class)]
#[Fillable(['parent_id', 'code', 'name', 'type', 'is_group', 'is_active', 'system_key', 'usali_department', 'sort_order', 'description'])]
class Account extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_group' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }
}
