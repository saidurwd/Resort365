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
     * @param  string|null  $label  shown in the template editor (defaults to the key)
     * @param  string|null  $description  when it is sent, for the template editor
     */
    public function __construct(
        public string $key,
        public string $channel,
        public ?string $subject,
        public string $body,
        public array $placeholders = [],
        public ?string $label = null,
        public ?string $description = null,
    ) {}
}
