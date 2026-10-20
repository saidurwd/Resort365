<?php

namespace Modules\Reports\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Reports\Database\Factories\ReportExportFactory;
use Modules\Reports\Enums\ExportFormat;
use Modules\Reports\Enums\ExportStatus;

/**
 * A report asked for as a file (Step 7.1): who asked, which report and filters, the format, and the file once it is made.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $report_key
 * @property string $title
 * @property ExportFormat $format
 * @property array<string, mixed> $filters
 * @property ExportStatus $status
 * @property string|null $path
 * @property string|null $error
 * @property int $rows_count
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 */
#[UseFactory(ReportExportFactory::class)]
#[Fillable(['user_id', 'report_key', 'title', 'format', 'filters', 'status', 'path', 'error', 'rows_count', 'completed_at', 'expires_at'])]
class ReportExport extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ReportExportFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['format' => ExportFormat::class, 'filters' => 'array', 'status' => ExportStatus::class, 'rows_count' => 'integer', 'completed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function fileName(): string
    {
        return str($this->report_key)->slug().'-'.$this->created_at->format('Ymd-His').'.'.$this->format->value;
    }
}
