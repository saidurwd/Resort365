<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\AnnouncementFactory;
use Modules\Platform\Enums\AnnouncementAudience;
use Modules\Platform\Enums\AnnouncementLevel;

/**
 * A message from the platform shown to tenants for a while (central, Step 7.3).
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property AnnouncementLevel $level
 * @property AnnouncementAudience $audience
 * @property list<int>|null $plan_ids
 * @property list<int>|null $tenant_ids
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 */
#[UseFactory(AnnouncementFactory::class)]
#[Fillable(['title', 'body', 'level', 'audience', 'plan_ids', 'tenant_ids', 'starts_at', 'ends_at', 'created_by'])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => AnnouncementLevel::class, 'audience' => AnnouncementAudience::class, 'plan_ids' => 'array', 'tenant_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }
}
