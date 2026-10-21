<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\PlatformAuditEntryFactory;

/**
 * One thing a platform admin did in the console (central, Step 7.3): who, to which tenant, what, and from where. Never changed.
 *
 * @property int $id
 * @property int|null $platform_admin_id
 * @property string $admin_name
 * @property int|null $tenant_id
 * @property string $action
 * @property string $summary
 * @property array<string, mixed>|null $details
 * @property string|null $ip
 * @property Carbon $created_at
 */
#[UseFactory(PlatformAuditEntryFactory::class)]
#[Fillable(['platform_admin_id', 'admin_name', 'tenant_id', 'action', 'summary', 'details', 'ip'])]
#[Table(name: 'platform_audit_log')]
class PlatformAuditEntry extends Model
{
    /** @use HasFactory<PlatformAuditEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }
}
