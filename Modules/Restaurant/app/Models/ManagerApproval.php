<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\ManagerApprovalFactory;

/**
 * One action on a shared POS terminal approved by a manager's PIN without the staff member signing
 * out (ARCHITECTURE §3.3 rule 6): what (action, the permission it needs, optional subject), who asked,
 * who approved, where. Valid for a few minutes and used once (ManagerApprovals). It is itself the
 * record of the approval, so it is not activity-logged.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $outlet_id
 * @property int|null $pos_terminal_id
 * @property string $action
 * @property string $permission
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int $requested_by
 * @property int $approved_by
 * @property string|null $reason
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 */
#[UseFactory(ManagerApprovalFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_terminal_id', 'action', 'permission', 'subject_type', 'subject_id', 'requested_by', 'approved_by', 'reason', 'expires_at', 'used_at',
])]
class ManagerApproval extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ManagerApprovalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
