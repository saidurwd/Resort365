<?php

namespace Modules\HR\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HR\Database\Factories\AttendanceImportFactory;

/**
 * A biometric CSV that was imported (Step 6.2): the file name and how many lines and days it carried.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $filename
 * @property int $rows_read
 * @property int $days_imported
 * @property int $days_skipped
 * @property int|null $imported_by
 */
#[UseFactory(AttendanceImportFactory::class)]
#[Fillable(['property_id', 'filename', 'rows_read', 'days_imported', 'days_skipped', 'imported_by'])]
class AttendanceImport extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<AttendanceImportFactory> */
    use HasFactory;
}
