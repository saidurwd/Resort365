<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\ApprovalStepFactory;

/**
 * One level of a workflow: approvers hold the permission, and the step applies to amounts from `min_amount`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $approval_workflow_id
 * @property int $level
 * @property string $label
 * @property string $permission
 * @property string $min_amount
 */
#[UseFactory(ApprovalStepFactory::class)]
#[Fillable(['approval_workflow_id', 'level', 'label', 'permission', 'min_amount'])]
class ApprovalStep extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalStepFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer', 'min_amount' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
