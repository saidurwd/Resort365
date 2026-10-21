<?php

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\TenantExportFactory;
use Modules\Core\Enums\TenantExportStatus;

/**
 * An export of all of a company's data (Step 7.4): asked for by its owner, or by a platform admin for a company that has left. The ZIP
 * lives in the tenant's storage until `expires_at`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $requested_by
 * @property string $requested_by_name
 * @property TenantExportStatus $status
 * @property string|null $path
 * @property int|null $size
 * @property int|null $tables_count
 * @property int|null $rows_count
 * @property int|null $files_count
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
#[UseFactory(TenantExportFactory::class)]
#[Fillable(['requested_by', 'requested_by_name', 'status', 'path', 'size', 'tables_count', 'rows_count', 'files_count', 'error', 'completed_at', 'expires_at'])]
class TenantExport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<TenantExportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => TenantExportStatus::class, 'completed_at' => 'datetime', 'expires_at' => 'datetime', 'size' => 'integer'];
    }

    public function fileName(): string
    {
        return 'data-export-'.$this->created_at->format('Y-m-d').'-'.$this->id.'.zip';
    }
}
