<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\LeaveTypeFactory;
use Modules\HR\Enums\Gender;

/**
 * A kind of leave (Step 6.3): paid or unpaid, whether it has a balance, whether weekends and holidays are charged, and who
 * may take it (maternity: women only). Its rules are in the `policy`.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property bool $is_paid
 * @property bool $has_balance
 * @property bool $counts_non_working_days
 * @property Gender|null $gender
 * @property string $color
 * @property int|null $document_after_days
 * @property bool $is_active
 * @property-read LeavePolicy|null $policy
 */
#[UseFactory(LeaveTypeFactory::class)]
#[Fillable(['code', 'name', 'is_paid', 'has_balance', 'counts_non_working_days', 'gender', 'color', 'document_after_days', 'is_active'])]
class LeaveType extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'has_balance' => 'boolean', 'counts_non_working_days' => 'boolean', 'gender' => Gender::class, 'document_after_days' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return HasOne<LeavePolicy, $this>
     */
    public function policy(): HasOne
    {
        return $this->hasOne(LeavePolicy::class);
    }
}
