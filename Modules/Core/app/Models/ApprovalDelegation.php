<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ApprovalDelegationFactory;

/**
 * A person lets another act on their approvals between two dates (Step 5.1).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $delegate_user_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 */
#[UseFactory(ApprovalDelegationFactory::class)]
#[Fillable(['user_id', 'delegate_user_id', 'starts_on', 'ends_on'])]
class ApprovalDelegation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalDelegationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
