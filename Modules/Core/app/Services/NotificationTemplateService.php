<?php

namespace Modules\Core\Services;

use InvalidArgumentException;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\RenderedTemplate;
use Modules\Core\Models\NotificationTemplate;

class NotificationTemplateService implements NotificationTemplates
{
    /**
     * @var array<string, NotificationTemplateDefinition>
     */
    private array $definitions = [];

    public function register(NotificationTemplateDefinition $definition): void
    {
        $this->definitions[$definition->key.':'.$definition->channel] = $definition;
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function render(string $key, string $channel, array $data = [], ?string $locale = null): RenderedTemplate
    {
        $locale ??= app()->getLocale();

        $override = NotificationTemplate::query()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->whereIn('locale', array_unique([$locale, 'en']))
            ->orderByRaw('locale = ? desc', [$locale])
            ->first();

        if ($override instanceof NotificationTemplate) {
            return $this->fill($override->subject, $override->body, $data);
        }

        $default = $this->definitions[$key.':'.$channel] ?? throw new InvalidArgumentException("No notification template [{$key}] for channel [{$channel}].");

        return $this->fill($default->subject !== null ? __($default->subject) : null, __($default->body), $data);
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    private function fill(?string $subject, string $body, array $data): RenderedTemplate
    {
        $replacements = [];

        foreach ($data as $name => $value) {
            $replacements['{'.$name.'}'] = (string) $value;
        }

        return new RenderedTemplate(
            $subject !== null ? strtr($subject, $replacements) : null,
            strtr($body, $replacements),
        );
    }
}
