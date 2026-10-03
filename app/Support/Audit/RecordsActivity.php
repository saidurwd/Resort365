<?php

namespace App\Support\Audit;

use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Audit logging for business models (Standard Step Rule R4, ARCHITECTURE §9.2): every create,
 * update and delete is recorded with the changed attributes (old and new) and who did it.
 * Hidden attributes (passwords, secrets, tokens), keys and timestamps (including soft deletes,
 * which appear as a "deleted" entry) are never logged as changes; see also
 * config('activitylog.default_except_attributes').
 *
 * Override activityLogExcept() to leave out more attributes.
 */
trait RecordsActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept([...$this->getHidden(), ...$this->activityLogExcept(), $this->getKeyName(), 'tenant_id', 'created_at', 'updated_at', 'deleted_at', 'property_scope'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(Str::snake(class_basename($this)))
            ->setDescriptionForEvent(fn (string $event): string => Str::headline(class_basename($this)).' '.$event);
    }

    /**
     * Extra attributes to keep out of the audit log.
     *
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return [];
    }
}
