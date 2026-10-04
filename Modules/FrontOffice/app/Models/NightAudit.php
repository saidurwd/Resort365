<?php

namespace Modules\FrontOffice\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\FrontOffice\Database\Factories\NightAuditFactory;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;

/**
 * One night audit of a property's business date (ARCHITECTURE §5.7, RunNightAudit). Unique per
 * property and date, so a completed date can never be audited again.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property Carbon $business_date
 * @property NightAuditStatus $status
 * @property NightAuditTrigger $trigger
 * @property int|null $started_by
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property int $nights_posted
 * @property int $no_shows
 * @property int $holds_released
 * @property list<string>|null $issues what stopped the audit
 * @property list<array{step: string, result: string}>|null $steps what each step did
 * @property string|null $error
 * @property Carbon|null $notified_at
 */
#[UseFactory(NightAuditFactory::class)]
#[Fillable([
    'property_id', 'business_date', 'status', 'trigger', 'started_by', 'started_at', 'completed_at', 'nights_posted', 'no_shows',
    'holds_released', 'issues', 'steps', 'error', 'notified_at',
])]
class NightAudit extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<NightAuditFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'status' => NightAuditStatus::class,
            'trigger' => NightAuditTrigger::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'notified_at' => 'datetime',
            'issues' => 'array',
            'steps' => 'array',
        ];
    }
}
