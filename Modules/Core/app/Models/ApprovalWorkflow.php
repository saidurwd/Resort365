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
use Modules\Core\Database\Factories\ApprovalWorkflowFactory;

/**
 * The approval workflow of one document type (Step 5.1): its steps decide who must approve which amounts.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $document_type
 * @property string $name
 * @property bool $is_active
 * @property-read Collection<int, ApprovalStep> $steps
 */
#[UseFactory(ApprovalWorkflowFactory::class)]
#[Fillable(['document_type', 'name', 'is_active'])]
class ApprovalWorkflow extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ApprovalWorkflowFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ApprovalStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('level');
    }
}
