<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\DiscountLimitFactory;

/**
 * The largest discount % a role may give on the POS without a manager's PIN (ARCHITECTURE §5.10.7).
 * Roles come from IAM (RoleDirectory); a role without a limit may not discount.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $role_id
 * @property string $max_percent
 */
#[UseFactory(DiscountLimitFactory::class)]
#[Fillable(['role_id', 'max_percent'])]
class DiscountLimit extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DiscountLimitFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['max_percent' => 'decimal:2'];
    }
}
