<?php

namespace Modules\HR\Models;

use App\Support\Attachments\HasPhotos;
use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\HR\Database\Factories\EmployeeFactory;
use Modules\HR\Enums\EmployeeStatus;
use Modules\HR\Enums\EmploymentType;
use Modules\HR\Enums\ExitReason;
use Modules\HR\Enums\Gender;
use Modules\HR\Enums\MaritalStatus;
use Spatie\MediaLibrary\HasMedia;

/**
 * A member of staff at one property (Step 6.1, ARCHITECTURE §5.15). The national ID, passport and bank account number
 * are encrypted and hidden, so they never reach the audit log or JSON; `national_id_hash` finds duplicates. Documents
 * and the photo are attachments. Salary belongs to Payroll (Step 6.4).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $employee_no
 * @property int|null $user_id
 * @property string $first_name
 * @property string|null $last_name
 * @property Gender|null $gender
 * @property MaritalStatus|null $marital_status
 * @property Carbon|null $date_of_birth
 * @property string|null $nationality_code
 * @property string|null $email
 * @property string|null $phone
 * @property array<string, string|null>|null $address
 * @property array<string, string|null>|null $emergency_contact
 * @property string|null $national_id
 * @property string|null $national_id_hash
 * @property string|null $passport_no
 * @property Carbon|null $passport_expiry
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_no
 * @property string|null $bank_branch
 * @property int $department_id
 * @property int $designation_id
 * @property int|null $grade_id
 * @property int|null $manager_id
 * @property EmploymentType $employment_type
 * @property Carbon $joined_on
 * @property Carbon|null $probation_ends_on
 * @property Carbon|null $confirmed_on
 * @property EmployeeStatus $status
 * @property Carbon|null $exited_on
 * @property ExitReason|null $exit_reason
 * @property string|null $notes
 * @property-read string $full_name
 * @property-read Designation $designation
 * @property-read Grade|null $grade
 * @property-read Employee|null $manager
 */
#[UseFactory(EmployeeFactory::class)]
#[Fillable([
    'employee_no', 'user_id', 'first_name', 'last_name', 'gender', 'marital_status', 'date_of_birth', 'nationality_code', 'email', 'phone', 'address',
    'emergency_contact', 'national_id', 'national_id_hash', 'passport_no', 'passport_expiry', 'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch',
    'property_id', 'department_id', 'designation_id', 'grade_id', 'manager_id', 'employment_type', 'joined_on', 'probation_ends_on', 'confirmed_on', 'status',
    'exited_on', 'exit_reason', 'notes',
])]
#[Hidden(['national_id', 'national_id_hash', 'passport_no', 'bank_account_no'])]
class Employee extends Model implements HasMedia
{
    use BelongsToProperty;
    use BelongsToTenant;
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    use HasPhotos;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'date_of_birth' => 'date',
            'address' => 'array',
            'emergency_contact' => 'array',
            'national_id' => 'encrypted',
            'passport_no' => 'encrypted',
            'passport_expiry' => 'date',
            'bank_account_no' => 'encrypted',
            'employment_type' => EmploymentType::class,
            'joined_on' => 'date',
            'probation_ends_on' => 'date',
            'confirmed_on' => 'date',
            'status' => EmployeeStatus::class,
            'exited_on' => 'date',
            'exit_reason' => ExitReason::class,
        ];
    }

    /**
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id')->withTrashed();
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /**
     * @return HasMany<EmployeeHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class)->orderByDesc('effective_on')->orderByDesc('id');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    public function isExited(): bool
    {
        return $this->status === EmployeeStatus::Exited;
    }

    /**
     * All but the last four characters masked (for people without hr.employee.view-sensitive).
     */
    public static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat('•', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
