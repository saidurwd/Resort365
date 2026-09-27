<?php

namespace Modules\Core\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\NotificationTemplateFactory;

/**
 * A tenant's wording for a notification (key + channel + locale), with {placeholders}.
 *
 * @property int $id
 * @property string $key
 * @property string $channel
 * @property string $locale
 * @property string|null $subject
 * @property string $body
 * @property bool $is_active
 */
#[UseFactory(NotificationTemplateFactory::class)]
#[Fillable(['key', 'channel', 'locale', 'subject', 'body', 'is_active'])]
class NotificationTemplate extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
