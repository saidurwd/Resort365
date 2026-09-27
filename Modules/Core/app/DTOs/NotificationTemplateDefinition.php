<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;

/**
 * Default wording a module registers for a notification; tenants may override it
 * (notification_templates). Placeholders look like {name}.
 */
final readonly class NotificationTemplateDefinition extends Data
{
    /**
     * @param  list<string>  $placeholders
     */
    public function __construct(
        public string $key,
        public string $channel,
        public ?string $subject,
        public string $body,
        public array $placeholders = [],
    ) {}
}
