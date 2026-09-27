<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\RenderedTemplate;

/**
 * Notification wording: the tenant's active template for key + channel + locale, else its
 * English one, else the registered default; {placeholders} are filled from $data.
 */
interface NotificationTemplates
{
    public function register(NotificationTemplateDefinition $definition): void;

    /**
     * @return array<string, NotificationTemplateDefinition> keyed "key:channel"
     */
    public function definitions(): array;

    /**
     * @param  array<string, scalar|null>  $data
     */
    public function render(string $key, string $channel, array $data = [], ?string $locale = null): RenderedTemplate;
}
