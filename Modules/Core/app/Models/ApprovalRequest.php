<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\ApprovalRequestFactory;
use Modules\Core\Enums\ApprovalStatus;

/**
 * A document waiting for approval (Step 5.1): the steps that apply to its amount are frozen on it, and `payload` holds what the owning module does once it is approved.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $property_id
 * @property int|null $approval_workflow_id
 * @property string $document_type
 * @property string $reference
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $amount
 * @property list<array{level: int, label: string, permission: string}> $steps
 * @property int $current_level
 * @property ApprovalStatus $status
 * @property array<string, mixed>|null $payload
 * @property int $requested_by
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property-read Collection<int, ApprovalAction> $actions
 */
#[UseFactory(ApprovalRequestFactory::class)]
#[Fillable(['property_id', 'approval_workflow_id', 'document_type', 'reference', 'subject_type', 'subject_id', 'amount', 'steps', 'current_level', 'status', 'payload', 'requested_by', 'decided_at'])]
class ApprovalRequest extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'steps' => 'array', 'payload' => 'array', 'status' => ApprovalStatus::class, 'decided_at' => 'datetime', 'current_level' => 'integer'];
    }

    /**
     * The step waiting for a decision.
     *
     * @return array{level: int, label: string, permission: string}|null
     */
    public function currentStep(): ?array
    {
        foreach ($this->steps as $step) {
            if ($step['level'] === $this->current_level) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)->orderBy('id');
    }
}
