<?php

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Models\Activity;

class AuditTrailService implements AuditTrail
{
    public function for(Model $subject, int $limit = 50): array
    {
        return Activity::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('causer')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): array => self::entry($activity))
            ->values()
            ->all();
    }

    /**
     * @return array{description: string, causer: ?string, at: CarbonInterface, changes: array<string, array{0: mixed, 1: mixed}>}
     */
    public static function entry(Activity $activity): array
    {
        $causer = $activity->causer;

        return [
            'description' => (string) $activity->description,
            'causer' => $causer instanceof Model ? (string) $causer->getAttribute('name') : null,
            'at' => $activity->created_at ?? now(),
            'changes' => self::changes($activity),
        ];
    }

    /**
     * field => [old, new] from the activity's attribute changes (and explicit old/new properties).
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function changes(Activity $activity): array
    {
        $changes = $activity->attribute_changes;
        $raw = $changes !== null && $changes->isNotEmpty() ? $changes : $activity->properties;
        $data = $raw?->all() ?? [];

        $new = (array) ($data['attributes'] ?? []);
        $old = (array) ($data['old'] ?? []);
        $changes = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $changes[(string) $field] = [self::scalar($old[$field] ?? null), self::scalar($new[$field] ?? null)];
        }

        return $changes;
    }

    private static function scalar(mixed $value): mixed
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }
}
