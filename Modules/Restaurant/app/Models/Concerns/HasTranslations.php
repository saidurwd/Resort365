<?php

namespace Modules\Restaurant\Models\Concerns;

/**
 * Multi-language text stored as json, one key per menu language (restaurant.menu_languages), e.g.
 * {"en": "Beef rezala", "bn": "গরুর রেজালা"}. The text shown is the asked language, else English,
 * else the first one there is.
 */
trait HasTranslations
{
    public function translated(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute);

        if (! is_array($values)) {
            return (string) $values;
        }

        $locale ??= app()->getLocale();

        foreach ([$locale, 'en'] as $key) {
            if (isset($values[$key]) && trim((string) $values[$key]) !== '') {
                return (string) $values[$key];
            }
        }

        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return '';
    }
}
