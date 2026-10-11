<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ApprovalActionFactory;

/**
 * One decision on an approval request (Step 5.1); `on_behalf_of` names the person whose right a delegate used.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $approval_request_id
 * @property int|null $level
 * @property string $action
 * @property int $user_id
 * @property int|null $on_behalf_of
 * @property string|null $comment
 * @property Carbon $acted_at
 */
#[UseFactory(ApprovalActionFactory::class)]
#[Fillable(['approval_request_id', 'level', 'action', 'user_id', 'on_behalf_of', 'comment', 'acted_at'])]
class ApprovalAction extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalActionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }
}
